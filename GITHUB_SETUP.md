# تعليمات إعداد GitHub Push

## الخيار 1: استخدام Personal Access Token (الأسهل)

### خطوات إنشاء Token:
1. اذهب إلى: https://github.com/settings/tokens
2. انقر على "Generate new token" → "Generate new token (classic)"
3. أعطِ الـ token اسم (مثلاً: "facturation_system")
4. اختر الصلاحيات: `repo` (Full control of private repositories)
5. انقر "Generate token"
6. **انسخ الـ token** (ستظهر مرة واحدة فقط!)

### استخدام الـ Token:
بعد الحصول على الـ Token، استخدم أحد الطرق التالية:

**الطريقة 1: في الـ URL مباشرة**
```bash
git remote set-url origin https://YOUR_TOKEN@github.com/TFarouki/facturation_system.git
git push -u origin master
```

**الطريقة 2: عند الطلب**
```bash
git push -u origin master
# Username: TFarouki
# Password: [الصق الـ Token هنا]
```

---

## الخيار 2: استخدام SSH Key

### إنشاء SSH Key:
```bash
ssh-keygen -t ed25519 -C "your_email@example.com"
# اضغط Enter للقيم الافتراضية
```

### إضافة المفتاح إلى GitHub:
```bash
cat ~/.ssh/id_ed25519.pub
# انسخ المحتوى وأضفه في: https://github.com/settings/ssh/new
```

### تغيير Remote إلى SSH:
```bash
git remote set-url origin git@github.com:TFarouki/facturation_system.git
git push -u origin master
```

---

## ملاحظة هامة:
- إذا كان المستودع على GitHub فارغاً، قد تحتاج لتغيير اسم الفرع:
  ```bash
  git branch -M main
  git push -u origin main
  ```

