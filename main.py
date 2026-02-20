from fastapi import FastAPI, Request, Response
import httpx
import hashlib
import os
import time

# 1. تهيئة تطبيق FastAPI
app = FastAPI()

# 2. إعداد مجلد التخزين المؤقت (Cache)
CACHE_DIR = "cache"
if not os.path.exists(CACHE_DIR):
    os.makedirs(CACHE_DIR)

# 3. إيقاف طلبات أيقونة الموقع (favicon) لتوفير الموارد
@app.get("/favicon.ico")
async def favicon():
    # إرجاع كود 204 (لا يوجد محتوى)
    return Response(status_code=204)

# 4. استقبال جميع الروابط الأخرى لمعالجتها
@app.api_route("/{path:path}", methods=["GET"])
async def proxy(request: Request, path: str):
    
    # أ. تحديد الرابط الوجهة (TMDB) بناءً على المسار
    if path.startswith("t/p/"):
        target_url = f"https://image.tmdb.org/{path}"
        cache_lifetime = 86400 * 30  # 30 يوماً للصور
    elif path.startswith("3/"):
        target_url = f"https://api.themoviedb.org/{path}"
        cache_lifetime = 3600  # ساعة واحدة للبيانات (API)
    else:
        return Response(content="مسار غير صحيح.", status_code=404)
    
    # ب. إضافة أي معلمات (Parameters) مثل api_key إلى الرابط
    query_string = request.url.query
    if query_string:
        target_url = f"{target_url}?{query_string}"

    # ج. إنشاء اسم ملف فريد (مشفّر) بناءً على الرابط الكامل
    cache_key = hashlib.md5(target_url.encode('utf-8')).hexdigest()
    cache_file = os.path.join(CACHE_DIR, cache_key)
    type_file = os.path.join(CACHE_DIR, f"{cache_key}_type")

    # د. التحقق من التخزين المؤقت (Cache)
    if os.path.exists(cache_file) and os.path.exists(type_file):
        file_age = time.time() - os.path.getmtime(cache_file)
        if file_age < cache_lifetime:
            # قراءة نوع الملف ومحتواه وإرساله فوراً (سريع جداً!)
            with open(type_file, 'r') as f:
                content_type = f.read()
            with open(cache_file, 'rb') as f:
                content = f.read()
            return Response(content=content, media_type=content_type)

    # هـ. إذا لم يكن في الـ Cache، نجلبه من TMDB بشكل غير متزامن (Asynchronous)
    async with httpx.AsyncClient() as client:
        # نرسل الطلب إلى TMDB
        tmdb_response = await client.get(target_url)
    
    # و. حفظ النتيجة في التخزين المؤقت إذا كان الطلب ناجحاً
    if tmdb_response.status_code == 200:
        # جلب نوع المحتوى (صورة أو JSON)
        content_type = tmdb_response.headers.get("content-type", "application/octet-stream")
        
        # حفظ البيانات ونوعها في مجلد cache
        with open(cache_file, 'wb') as f:
            f.write(tmdb_response.content)
        with open(type_file, 'w') as f:
            f.write(content_type)
            
        # إرسال النتيجة للمستخدم
        return Response(content=tmdb_response.content, status_code=200, media_type=content_type)
    
    # ز. إذا فشل الطلب من TMDB (مثلاً صورة غير موجودة)، نرسل رسالة الخطأ كما هي
    return Response(content=tmdb_response.content, status_code=tmdb_response.status_code)
