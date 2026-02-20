<?php
// 1. الحصول على المسار المطلوب
$request_uri = $_SERVER['REQUEST_URI'];

// ==========================================
// الكود الجديد: إيقاف طلبات أيقونة الموقع (favicon)
// ==========================================
if (strpos($request_uri, 'favicon.ico') !== false) {
    http_response_code(204); // إرسال رسالة للمتصفح: "لا يوجد محتوى هنا"
    exit; // إيقاف تنفيذ السكربت فوراً لتوفير موارد الخادم
}
// ==========================================

// 2. إعداد مجلد التخزين المؤقت (Cache)
$cache_dir = __DIR__ . '/cache/';
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// 3. إنشاء اسم ملف فريد بناءً على الرابط المطلوب
$cache_filename = md5($request_uri);
$cache_file = $cache_dir . $cache_filename;
$cache_type_file = $cache_dir . $cache_filename . '_type';

// 4. تحديد مدة صلاحية التخزين المؤقت
$is_image = strpos($request_uri, '/t/p/') !== false;
$cache_lifetime = $is_image ? (86400 * 30) : 3600; 

// 5. التحقق مما إذا كان الملف موجوداً في التخزين المؤقت
if (file_exists($cache_file) && file_exists($cache_type_file)) {
    $file_age = time() - filemtime($cache_file);
    
    if ($file_age < $cache_lifetime) {
        $content_type = file_get_contents($cache_type_file);
        header('Content-Type: ' . $content_type);
        readfile($cache_file);
        exit;
    }
}

// 6. تحديد الرابط الوجهة (TMDB)
$target_url = '';
if ($is_image) {
    $target_url = 'https://image.tmdb.org' . $request_uri;
} else if (strpos($request_uri, '/3/') !== false) {
    $target_url = 'https://api.themoviedb.org' . $request_uri;
} else {
    // إذا كان الرابط غير معروف، نوقف العمل
    http_response_code(404);
    die('مسار غير صحيح.');
}

// 7. جلب البيانات من TMDB باستخدام cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $target_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, false);

$headers = [];
curl_setopt($ch, CURLOPT_HEADERFUNCTION,
    function($curl, $header) use (&$headers) {
        $len = strlen($header);
        $header_parts = explode(':', $header, 2);
        if (count($header_parts) < 2) return $len;
        $headers[strtolower(trim($header_parts[0]))][] = trim($header_parts[1]);
        return $len;
    }
);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 8. حفظ النتيجة في التخزين المؤقت إذا كان الطلب ناجحاً
if ($http_code == 200) {
    file_put_contents($cache_file, $response);
    if (isset($headers['content-type'])) {
        file_put_contents($cache_type_file, $headers['content-type'][0]); 
    }
}

// 9. إرسال النتيجة النهائية للمتصفح
http_response_code($http_code);
if (isset($headers['content-type'])) {
    header('Content-Type: ' . $headers['content-type'][0]);
}
echo $response;
?>
