# 1. نطلب من Render استخدام خادم جاهز يحتوي على لغة PHP وإصدار Apache
FROM php:8.2-apache

# 2. تفعيل ميزة "إعادة كتابة الروابط" لكي يعمل ملف .htaccess الخاص بنا
RUN a2enmod rewrite

# 3. نسخ جميع ملفات مشروعنا (index.php و .htaccess) إلى مجلد الخادم الأساسي
COPY . /var/www/html/

# 4. إنشاء مجلد التخزين المؤقت (cache) وإعطائه صلاحيات الكتابة لكي لا نواجه أخطاء
RUN mkdir -p /var/www/html/cache \
    && chown -R www-data:www-data /var/www/html/cache \
    && chmod -R 777 /var/www/html/cache

# 5. إخبار المنصة بأن خادم الويب سيعمل على المنفذ 80
EXPOSE 80
