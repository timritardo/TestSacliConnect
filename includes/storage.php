<?php
/**
 * includes/storage.php
 * Supabase Storage helper — replaces local move_uploaded_file() calls.
 *
 * Usage:
 *   $url = uploadToSupabase($tmp_path, $destination_filename);
 *   if ($url) { // save $url to DB } else { // upload failed }
 *
 * Returns the public URL string on success, or false on failure.
 */

define('SUPABASE_URL',         getenv('SUPABASE_URL')         ?: 'https://aqtmavkjcugkqwjfrjwr.supabase.co');
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImFxdG1hdmtqY3Vna3F3amZyandyIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4Mjc3MjU0NSwiZXhwIjoyMDk4MzQ4NTQ1fQ.wmCthcmlgYgg-vuQfrh45BbgED11h_C6vHuLw6WHisc');
define('SUPABASE_BUCKET',      getenv('SUPABASE_BUCKET')      ?: 'sacliconnect');

/**
 * Upload a file to Supabase Storage.
 *
 * @param string $tmp_path       Local temp file path (from $_FILES[...]['tmp_name'])
 * @param string $filename       Destination filename inside the bucket (e.g. "profile_123.jpg")
 * @param string $mime_type      MIME type (e.g. "image/jpeg"). Auto-detected if empty.
 * @return string|false          Public URL on success, false on failure.
 */
function uploadToSupabase(string $tmp_path, string $filename, string $mime_type = ''): string|false {
    if (!file_exists($tmp_path)) return false;

    // Auto-detect MIME type if not provided
    if (empty($mime_type)) {
        $mime_type = mime_content_type($tmp_path) ?: 'application/octet-stream';
    }

    $endpoint = SUPABASE_URL . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . ltrim($filename, '/');

    $file_data = file_get_contents($tmp_path);
    if ($file_data === false) return false;

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $file_data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $mime_type,
            'x-upsert: true',   // overwrite if file already exists
        ],
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 200 or 200-range means success
    if ($http_code >= 200 && $http_code < 300) {
        return SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/' . ltrim($filename, '/');
    }

    // Log failure silently
    error_log("Supabase upload failed [$http_code]: $response — file: $filename");
    return false;
}

/**
 * Delete a file from Supabase Storage.
 *
 * @param string $filename  Filename inside the bucket (e.g. "profile_123.jpg")
 * @return bool
 */
function deleteFromSupabase(string $filename): bool {
    $endpoint = SUPABASE_URL . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . ltrim($filename, '/');

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        ],
    ]);

    $http_code = curl_getinfo(curl_exec($ch) ? $ch : $ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code >= 200 && $http_code < 300;
}

/**
 * Get the public URL for a stored filename.
 * Use this in <img src="..."> and anywhere a URL is needed.
 *
 * @param string $filename  Filename stored in DB (could be a full URL already or just a filename)
 * @return string
 */
function storageUrl(string $filename): string {
    if (empty($filename)) return '';
    // Already a full URL (Supabase or external)
    if (str_starts_with($filename, 'http')) return $filename;
    // Legacy local file — serve from uploads/ folder (still works locally)
    if (str_starts_with($filename, 'uploads/')) return $filename;
    // Plain filename — build Supabase URL
    return SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/' . $filename;
}
