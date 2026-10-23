<?php
set_time_limit(300);
header('Access-Control-Allow-Origin: *');

function logTerm($msg) {
    file_put_contents('php://stderr', "[LinkToMusic] $msg\n");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['detail' => 'Method not allowed']);
    exit;
}

function sendError($msg, $code = 400) {
    logTerm("ERROR: $msg");
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['detail' => $msg]);
    exit;
}

function cleanupDir($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = "$dir/$item";
        is_dir($path) ? cleanupDir($path) : @unlink($path);
    }
    @rmdir($dir);
}

$link     = trim($_POST['link'] ?? '');
$format   = $_POST['audio_format'] ?? 'flac';
$coverUrl = trim($_POST['cover_url'] ?? '');
$albumUrl = trim($_POST['album_url'] ?? '');

if (!in_array($format, ['wav', 'flac', 'm4a'], true)) {
    sendError('Unsupported audio format.');
}

$jobId  = uniqid('job_', true);
$jobDir = __DIR__ . '/temp_downloads/' . $jobId;
if (!mkdir($jobDir, 0777, true)) {
    sendError('Failed to create temporary folder.', 500);
}

$rawAudioPath = '';
$title = 'converted_track';

// 1. Get Source Audio (Uploaded File OR Link via single-pass yt-dlp)
if (!empty($_FILES['audio_file']['tmp_name']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
    $origName = $_FILES['audio_file']['name'];
    $title = pathinfo($origName, PATHINFO_FILENAME);
    $ext   = pathinfo($origName, PATHINFO_EXTENSION);
    $rawAudioPath = "$jobDir/source.$ext";
    move_uploaded_file($_FILES['audio_file']['tmp_name'], $rawAudioPath);
    logTerm("Loaded local audio: $origName");
} elseif ($link !== '') {
    logTerm("Downloading audio via yt-dlp: $link");
    $outTmpl   = "$jobDir/source.%(ext)s";
    $titleFile = "$jobDir/title.txt";

    $dlCmd = sprintf(
        'yt-dlp --no-playlist -f "bestaudio/best" --print-to-file "%%(title)s" %s -o %s %s 2>&1',
        escapeshellarg($titleFile),
        escapeshellarg($outTmpl),
        escapeshellarg($link)
    );
    exec($dlCmd, $dlOutput, $dlReturn);

    if (file_exists($titleFile)) {
        $fetchedTitle = trim(file_get_contents($titleFile));
        $title = preg_replace('/[^\w\s\-\.\(\)\[\]]/u', '', $fetchedTitle);
        $title = trim($title) ?: 'downloaded_audio';
        @unlink($titleFile);
    }

    $matches = glob("$jobDir/source.*");
    if ($dlReturn !== 0 || empty($matches)) {
        cleanupDir($jobDir);
        sendError("Failed to download link: " . implode(" | ", array_slice($dlOutput, -3)), 500);
    }
    $rawAudioPath = $matches[0];
    logTerm("Downloaded audio: $rawAudioPath (Title: $title)");
} else {
    cleanupDir($jobDir);
    sendError('Provide an input link or select a local audio file.');
}

// 2. Download/Save & Auto-Resize Image to 1024x1024
function fetchUrlBytes($url) {
    $parsed  = parse_url($url);
    $referer = isset($parsed['scheme'], $parsed['host'])
        ? $parsed['scheme'] . '://' . $parsed['host'] . '/'
        : $url;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0',
        CURLOPT_REFERER        => $referer,
        CURLOPT_HTTPHEADER     => [
            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $data !== false && strlen($data) > 100) {
        return $data;
    }
    return false;
}

function prepareArtwork($jobDir, $name, $fileKey, $url) {
    $rawPath   = "$jobDir/{$name}_raw";
    $cleanPath = "$jobDir/{$name}_clean.png";
    $hasInput  = false;

    if (!empty($_FILES[$fileKey]['tmp_name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $hasInput = true;
        move_uploaded_file($_FILES[$fileKey]['tmp_name'], $rawPath);
    } elseif ($url !== '') {
        $hasInput = true;
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'error' => "Invalid $name image URL."];
        }
        logTerm("Downloading $name image: $url");
        $data = fetchUrlBytes($url);
        if ($data !== false) {
            file_put_contents($rawPath, $data);

            // If user pasted a webpage URL instead of a direct image, extract og:image
            if (@getimagesize($rawPath) === false) {
                $html = file_get_contents($rawPath);
                if (preg_match('/<meta[^>]+(?:property|name)=["\'](?:og:image|twitter:image)["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m) ||
                    preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\'](?:og:image|twitter:image)["\']/i', $html, $m)) {
                    $ogUrl = html_entity_decode($m[1]);
                    logTerm("Extracted direct image from webpage: $ogUrl");
                    $ogData = fetchUrlBytes($ogUrl);
                    if ($ogData !== false) {
                        file_put_contents($rawPath, $ogData);
                    }
                }
            }
        }
    }

    if (!$hasInput) {
        return ['ok' => true, 'path' => null];
    }

    $imgInfo = @getimagesize($rawPath);
    if (!file_exists($rawPath) || $imgInfo === false) {
        return [
            'ok'    => false,
            'error' => "The site hosting your $name image blocked direct server downloads. Right-click the image in your browser, click 'Copy Image', and press Ctrl+V inside the image link box!"
        ];
    }

    $width  = $imgInfo[0];
    $height = $imgInfo[1];

    // Auto-resize any image smaller than 1024x1024 up to 1024x1024
    if ($width < 1024 || $height < 1024) {
        logTerm("Image is too small ({$width}x{$height}). Auto-resizing to 1024x1024...");
        $scaleFilter = "scale=1024:1024:flags=lanczos";
    } else {
        logTerm("Image size is {$width}x{$height}. Keeping high resolution.");
        $scaleFilter = "scale='min(1200,iw)':'min(1200,ih)':force_original_aspect_ratio=decrease:flags=lanczos,scale=trunc(iw/2)*2:trunc(ih/2)*2";
    }

    // Convert to clean 24-bit RGB PNG
    $normCmd = sprintf(
        'ffmpeg -y -i %s -vf "%s,format=rgb24" -frames:v 1 -c:v png %s 2>&1',
        escapeshellarg($rawPath),
        $scaleFilter,
        escapeshellarg($cleanPath)
    );
    exec($normCmd, $normOut, $normRet);

    if ($normRet !== 0 || !file_exists($cleanPath) || filesize($cleanPath) === 0) {
        return [
            'ok'    => false,
            'error' => "Failed to process $name image: " . implode(" | ", array_slice($normOut, -3))
        ];
    }

    logTerm("Prepared 1024x1024 artwork for $name.");
    return ['ok' => true, 'path' => $cleanPath];
}

$coverRes = prepareArtwork($jobDir, 'cover', 'cover_file', $coverUrl);
if (!$coverRes['ok']) {
    cleanupDir($jobDir);
    sendError($coverRes['error'], 400);
}

$albumRes = prepareArtwork($jobDir, 'album', 'album_file', $albumUrl);
if (!$albumRes['ok']) {
    cleanupDir($jobDir);
    sendError($albumRes['error'], 400);
}

$primaryArt = $coverRes['path'] ?: $albumRes['path'];

// 3. Convert to WAV / FLAC / M4A & Embed Artwork via FFmpeg
$outputFilename = "$title.$format";
$outputPath     = "$jobDir/$outputFilename";

// Unique artist + album tags prevent VLC from reusing old cached .m4a cover art
$shortTag   = substr(md5($jobId), 0, 5);
$artistMeta = "LinkToMusic";
$albumMeta  = "$title [$shortTag]";

if ($primaryArt && $format !== "wav") {
    logTerm("Converting to .$format WITH embedded 1024x1024 cover art...");
    if ($format === 'flac') {
        $ffmpegCmd = sprintf(
            'ffmpeg -y -i %s -i %s -map_metadata -1 -map 0:a:0 -map 1:v:0 -c:a flac -c:v copy -disposition:v:0 attached_pic -metadata:s:v:0 title="Album cover" -metadata:s:v:0 comment="Cover (front)" -metadata title=%s -metadata artist=%s -metadata album=%s %s 2>&1',
            escapeshellarg($rawAudioPath),
            escapeshellarg($primaryArt),
            escapeshellarg($title),
            escapeshellarg($artistMeta),
            escapeshellarg($albumMeta),
            escapeshellarg($outputPath)
        );
    } elseif ($format === 'm4a') {
        $ffmpegCmd = sprintf(
            'ffmpeg -y -i %s -i %s -map_metadata -1 -map 0:a:0 -map 1:v:0 -c:a aac -b:a 256k -c:v copy -disposition:v:0 attached_pic -metadata:s:v:0 title="Album cover" -metadata:s:v:0 comment="Cover (front)" -metadata title=%s -metadata artist=%s -metadata album=%s -movflags +faststart %s 2>&1',
            escapeshellarg($rawAudioPath),
            escapeshellarg($primaryArt),
            escapeshellarg($title),
            escapeshellarg($artistMeta),
            escapeshellarg($albumMeta),
            escapeshellarg($outputPath)
        );
    } else {
        $ffmpegCmd = sprintf(
            'ffmpeg -y -i %s -i %s -map_metadata -1 -map 0:a:0 -map 1:v:0 -c:a pcm_s16le -c:v copy -disposition:v:0 attached_pic -metadata:s:v:0 title="Album cover" -metadata:s:v:0 comment="Cover (front)" -metadata title=%s -metadata artist=%s -metadata album=%s -id3v2_version 3 -write_id3v2 1 %s 2>&1',
            escapeshellarg($rawAudioPath),
            escapeshellarg($primaryArt),
            escapeshellarg($title),
            escapeshellarg($artistMeta),
            escapeshellarg($albumMeta),
            escapeshellarg($outputPath)
        );
    }
} else {
    logTerm("Converting to .$format (audio only, no artwork provided)...");
    if ($format === 'wav') {
        $audioCodec = '-vn -c:a pcm_s16le';
    } elseif ($format === 'flac') {
        $audioCodec = '-vn -c:a flac';
    } else {
        $audioCodec = '-vn -c:a aac -b:a 256k -movflags +faststart';
    }
    $ffmpegCmd = sprintf(
        'ffmpeg -y -i %s -map_metadata -1 %s -metadata title=%s %s 2>&1',
        escapeshellarg($rawAudioPath),
        $audioCodec,
        escapeshellarg($title),
        escapeshellarg($outputPath)
    );
}

exec($ffmpegCmd, $ffOutput, $ffReturn);

if ($ffReturn !== 0 || !file_exists($outputPath)) {
    cleanupDir($jobDir);
    sendError("FFmpeg error: " . implode("\n", array_slice($ffOutput, -5)), 500);
}

// Automatically clear local VLC art cache on Linux so stale .m4a covers never stick
$homeDir = getenv('HOME') ?: (posix_getpwuid(posix_geteuid())['dir'] ?? '');
if ($homeDir && is_dir("$homeDir/.cache/vlc/art")) {
    cleanupDir("$homeDir/.cache/vlc/art");
    logTerm("Cleared local VLC art cache.");
}

logTerm("Success! Streaming $outputFilename to browser.");

// 4. Send File to User's Browser & Clean Up Server
$safeAscii = preg_replace('/[^\x20-\x7E]/', '_', $outputFilename);
$utf8Name  = rawurlencode($outputFilename);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . addslashes($safeAscii) . '"; filename*=UTF-8\'\'' . $utf8Name);
header('Content-Length: ' . filesize($outputPath));
header('Cache-Control: no-cache');
flush();

readfile($outputPath);
cleanupDir($jobDir);
exit;