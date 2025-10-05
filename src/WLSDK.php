<?php
namespace WebLiveHub\SDK;

/**
 * WLSDK (Composer version skeleton)
 * NOTE: This is an initial migrated copy; will be refined (DI, exceptions, no inline style) in later steps.
 */
class WLSDK {
  public const VERSION = '1.0.2';
  public const VERSION_CODE = 'v1_0_2';
  private static array $config = [];
  private static ?string $lastError = null;
  private static ?string $SDK_CDN_BASE = null;

  private static $is_slug = false; // >=1.0.1-dev
  private static $slug = null; 

  private static $DEFAULT_CND_BASE = 'https://console.weblivehub.com'; 

  /**
   * Configure SDK CDN base URL
   * @param string $cdnBase CDN base URL
   */
  public static function configure(string $cdnBase = null): void {
    if ($cdnBase !== null) {
      self::$SDK_CDN_BASE = rtrim($cdnBase, '/');
    }
  }

  private static function getSDKCDNBase(): string {
    // Use manually configured value if available
    if (self::$SDK_CDN_BASE !== null) {
      return self::$SDK_CDN_BASE;
    }

    // Default value
    return self::$DEFAULT_CND_BASE;
  }

  public static function setup(array $config): bool {
    self::$config = $config + self::$config;
    // Normalize hb_endpoint if provided without scheme so that HTTP request executes PHP instead of reading source.
    if (!empty(self::$config['hb_endpoint']) && is_string(self::$config['hb_endpoint'])) {
      $b = self::$config['hb_endpoint'];
      if (!preg_match('#^https?://#i', $b)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (str_starts_with($b, '/')) {
          $b = $scheme . '://' . $host . $b; // absolute path on current host
        } else {
          $b = $scheme . '://' . $host . '/' . ltrim($b, '/'); // relative path
        }
      }
      // Hosted backend slug detection (>=1.0.1): pattern /WL_HOST/<40hex>/wl_api/backend*.php
      if (preg_match('#/WL_HOST/([a-f0-9]{40})/wl_api/backend[^/]*\.php$#i', $b, $m)) {
        self::$slug = $m[1];
        self::$is_slug = true;
      }
      self::$config['hb_endpoint'] = $b;
    }
    self::$lastError = null;
    return true;
  }

  /** Get last error */
  public static function lastError(): ?string { return self::$lastError; }
  
  /** Emit <script> tag for embed.js */
  
  public static function script(): string {
    $cdnBase = self::getSDKCDNBase();
    if (!self::$is_slug || empty(self::$slug)) {
      return '<script src="'.$cdnBase.'/sdk-assets/'.self::VERSION_CODE.'/js/embed.js"></script>';
    }
    
    // Prefer versioned path; fallback to legacy path if not available
    $versionedPath = '/WL_HOST/'.self::$slug.'/'.self::VERSION_CODE.'/js/embed.js';
    $legacyPath = '/WL_HOST/'.self::$slug.'/js/embed.js';
    
    // In production, assume versioned path exists
    // This avoids the overhead of checking file existence on every request
    return '<script src="'.$cdnBase.$versionedPath.'" onerror="this.onerror=null;this.src=\''.$cdnBase.$legacyPath.'\'"></script>';
  }

  /** Internal helper to POST application/x-www-form-urlencoded */
  private static function postForm(string $url, array $fields, int $timeout = 15): array {
    $payload = http_build_query($fields, '', '&');

    $resp = false;
    $httpCode = null;
    $errDetail = null;

    // Prefer cURL if available for better diagnostics
    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
          'Content-Type: application/x-www-form-urlencoded',
          'Accept: application/json',
          'User-Agent: WLSDK/'.self::VERSION
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
      ]);
      $body = curl_exec($ch);
      if ($body !== false && $body !== '') {
        $resp = $body;
      }
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: null;
      if ($resp === false) {
        $errDetail = curl_error($ch) ?: null;
      }
      curl_close($ch);
    }

    // Fallback to stream if cURL unavailable or yielded empty string
    if ($resp === false) {
      $opts = ['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\nUser-Agent: WLSDK/".self::VERSION,
        'content' => $payload,
        'timeout' => $timeout,
        'ignore_errors' => true,
      ]];
      $ctx = stream_context_create($opts);
      $streamBody = @file_get_contents($url, false, $ctx);
      if ($streamBody !== false && $streamBody !== '') {
        $resp = $streamBody;
        // Attempt to parse HTTP status from headers if possible
        if (isset($http_response_header) && is_array($http_response_header)) {
          foreach ($http_response_header as $hdr) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $hdr, $m)) { $httpCode = (int)$m[1]; break; }
          }
        }
      }
    }

    if ($resp === false) {
      // Network-layer failure
      return ['__raw' => null, '__http' => $httpCode, '__err' => $errDetail ?: 'network_error'];
    }

    // HTTP error (4xx/5xx) still try to parse JSON to get structured error
    $data = json_decode($resp, true);
    if (is_array($data)) {
      if ($httpCode !== null) $data['__http'] = $httpCode; // annotate for callers if needed
      return $data;
    }
    // Edge case: backend double-encoded JSON string
    if (is_string($data)) {
      $second = json_decode($data, true);
      if (is_array($second)) {
        if ($httpCode !== null) $second['__http'] = $httpCode;
        return $second;
      }
    }

    return ['__raw' => $resp, '__http' => $httpCode];
  }

  /** Fetch a stream token immediately (server-side) */
  private static function fetchStreamToken(string $hostLabel, string $streamer): ?string {
    self::$lastError = null;
    $backend = self::$config['hb_endpoint'] ?? null;
    $user_id = self::$config['user_id'] ?? null;
    $password = self::$config['password'] ?? null;

    $debug = (bool)(self::$config['debug'] ?? false);
    if (!$backend || !$user_id || !$password) { self::$lastError = 'Missing required config'; return null; }
    if ($hostLabel === '' || $streamer === '') {
      self::$lastError = 'hostLabel and streamer are required.';
      return null;
    }
    $data = self::postForm($backend, [
      'action' => 'generate_token',
      'label' => $hostLabel,
      'streamer' => $streamer,
      'userAuth' => json_encode(['user_id' => $user_id, 'password' => $password], JSON_UNESCAPED_UNICODE)
    ], 30);
    if (!is_array($data) || isset($data['__raw'])) {
      self::$lastError = 'Invalid JSON response.';
      return null;
    }
    $statusOk = false;
    if (isset($data['status'])) {
      $statusOk = (int)$data['status'] === 1;
    } elseif (isset($data['success'])) {
      $statusOk = ($data['success'] === true || $data['success'] === 1 || $data['success'] === '1');
    }
    if (!$statusOk) {
      self::$lastError = $data['message'] ?? $data['err'] ?? 'Request failed';
      return null;
    }
    $token = $data['data']['token'] ?? $data['token'] ?? null;
    if (!$token) {
      self::$lastError = $data['message'] ?? 'Token missing in response.';
      return null;
    }
    return $token;
  }

  /** Ensure viewer auth token (used by lazy iframes) */
  private static function ensureAuthToken(): bool {
    if (!empty(self::$config['authToken'])) return true;
    $backend = self::$config['hb_endpoint'] ?? '';
    $user_id = self::$config['user_id'] ?? '';
    $password = self::$config['password'] ?? '';
    if ($backend === '' || $user_id === '' || $password === '') {
       self::$lastError = 'Missing hb_endpoint / user_id / password';
       return false;
    }
    $data = self::postForm($backend,[
      'action' => 'issue_auth_token',
      'userAuth' => json_encode(['user_id' => $user_id,
      'password' => $password], JSON_UNESCAPED_UNICODE)
    ]);
    if (!is_array($data) || isset($data['__raw'])) {
      self::$lastError = 'Invalid JSON issuing authToken';
      return false;
    }
    $token = $data['data']['authToken'] ?? $data['authToken'] ?? null;
    if (!$token) { 
      self::$lastError = $data['message'] ?? 'authToken missing';
      return false;
    }
    self::$config['authToken'] = $token;
    return true;
  }

  /** Build inline safe attribute list */
  private static function buildAttributes(array $pairs): string {
    $out = [];
    foreach ($pairs as $k => $v) {
      if (!is_string($k) || $k === '') continue;
      if ($k === 'class') {
        $existing = '';
        if ($v === null || $v === true) {
          $existing = 'wl-stream-div';
        } else {
          $classes = preg_split('/\s+/', trim((string)$v)) ?: [];
          if (!in_array('wl-stream-div', $classes, true)) {
            $classes[] = 'wl-stream-div';
          }
          $existing = trim(implode(' ', array_filter($classes, static function($c){ return $c !== ''; })));
          if ($existing === '') {
            $existing = 'wl-stream-div';
          }
        }
        $v = $existing;
      }
      if ($v === null) {
        $out[] = htmlspecialchars($k, ENT_QUOTES, 'UTF-8');
      } else {
        $out[] = htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '"';
      }
    }
    return implode(' ', $out);
  }

  public static function iframe(array $opts) { 
    $hostLabel = $opts['hostLabel'] ?? '';
    $streamer  = $opts['streamer'] ?? '';
    if ($hostLabel === '' || $streamer === '') {
      return '<div class="wl-stream-error">hostLabel & streamer required</div>';
    }
    $token = self::fetchStreamToken($hostLabel, $streamer);
    if (!$token) {
      $msg = htmlspecialchars(self::$lastError ?? 'Unknown error', ENT_QUOTES, 'UTF-8');
      return '<div class="wl-stream-error">' . $msg . '</div>';
    }
    $attrsInput = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
    $id = $opts['id'] ?? ($attrsInput['id'] ?? ('wlstream_' . substr(bin2hex(random_bytes(6)), 0, 12)));
    $basePairs = [
      'id' => $id,
      'host-label' => $hostLabel,
      'iframe-token' => $token,
    ];
    $attrs = $attrsInput;
    $reserved = ['host-label','iframe-token','id'];
    foreach ($attrs as $k => $v) {
      if (!is_string($k) || $k === '') continue;
      $lower = strtolower($k);
      if (in_array($lower, $reserved, true)) continue;
      if (!preg_match('/^[a-zA-Z0-9_:-]+$/', $k)) continue;
      if ($v === true) {
        $basePairs[$k] = null;
      } elseif ($v !== false && $v !== null) {
        $basePairs[$k] = (string)$v;
      }
    }
    $html = '<wl-stream ' . self::buildAttributes($basePairs) . '></wl-stream>';
    
    $events = is_array($opts['events'] ?? null) ? $opts['events'] : [];
    if ($events) {
      $lines = [];
      foreach ($events as $evt => $handlerBody) {
        if (!is_string($evt) || !preg_match('/^[a-zA-Z0-9_:-]+$/', $evt)) continue;
        if (!is_string($handlerBody) || $handlerBody === '') continue;
        $safeBody = str_replace(['</script','<script'], ['</scri'.'pt','<scri'.'pt'], $handlerBody);
        $lines[] = 'el.addEventListener(' . json_encode($evt) . ', function(event){ try { ' . $safeBody . ' } catch(e){ console.error("WLSDK event handler error", e); } });';
      }
      if ($lines) {
        $html .= "\n<script>(function(){var el=document.getElementById(" . json_encode($id) . ");if(!el)return;" . implode('', $lines) . "})();</script>";
      }
    }
    return $html;
  }

  public static function lazyIframe(array $opts): string {
    $hostLabel = $opts['hostLabel'] ?? '';
    $streamer = $opts['streamer'] ?? '';
    if ($hostLabel === '' || $streamer === '') {
      return '<div class="wl-stream-error">hostLabel & streamer required</div>';
    }
    $authToken = $opts['authToken'] ?? (self::$config['authToken'] ?? '');
    if ($authToken === '') {
      if (!self::ensureAuthToken()) { 
        $msg = htmlspecialchars(self::$lastError ?? 'auth token error', ENT_QUOTES, 'UTF-8');
        return '<div class="wl-stream-error">' . $msg . '</div>';
      }
      $authToken = self::$config['authToken'];
    }
     
    if(!self::$is_slug){ // >= 1.0.1
      $hb_endpoint = self::$config['hb_endpoint'] ?? ($opts['hb_endpoint'] ?? '');
      if ($hb_endpoint === '') { 
        return '<div class="wl-stream-error">hb_endpoint missing</div>'; 
      }
      $pairs = [
        'host-label' => $hostLabel,
        'streamer' => $streamer,
        'wl-endpoint' => $hb_endpoint
      ]; 
      if ($authToken !== '') $pairs['auth-token'] = $authToken; 
      $extra = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : []; 
      foreach ($extra as $k => $v) { 
        if (!is_string($k) || $k === '' || isset($pairs[$k])) continue; 
        if ($v === true) {
          $pairs[$k] = null;
        } elseif ($v !== false && $v !== null) {
          $pairs[$k] = (string)$v;
        } 
      } 
    }
    else{
      $pairs=[
        'host-label'=>$hostLabel,
        'streamer'=>$streamer
      ]; 
      if($authToken!=='')
        $pairs['auth-token'] = $authToken;
      $extra = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : []; foreach($extra as $k=>$v){ 
        if(!is_string($k)||$k===''||isset($pairs[$k])) 
          continue;
        if($v===true){ 
          $pairs[$k]=null; 
        } elseif($v!==false && $v!==null){ 
          $pairs[$k]=(string)$v; 
        } 
      } 
    }
    
    return '<wl-stream-lazy ' . self::buildAttributes($pairs) . '></wl-stream-lazy>';
  }
}
