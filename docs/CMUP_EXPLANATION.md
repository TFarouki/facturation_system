# شرح حساب CMUP (Weighted Average Cost)

## ما هو CMUP؟

**CMUP** تعني **Cumulative Moving Average Price** أو **Weighted Average Cost** (الوسط المرجح للتكلفة).

هو متوسط سعر الشراء المرجح للمنتج بناءً على جميع مشترياته.

## الصيغة الرياضية

```
CMUP = مجموع (سعر الشراء × الكمية) / مجموع الكميات
```

أو:

```
CMUP = Total Cost / Total Quantity

حيث:
Total Cost = Σ(سعر الشراء × الكمية) لجميع المشتريات
Total Quantity = Σ(الكميات) لجميع المشتريات
```

## مثال توضيحي

### مثال 1: مشتريات بسيطة

لنفترض أن لدينا منتج "Wireless Mouse":

**المشتريات:**
1. الفاتورة الأولى: 10 قطع × 50 DH = 500 DH
2. الفاتورة الثانية: 20 قطعة × 55 DH = 1,100 DH
3. الفاتورة الثالثة: 15 قطعة × 60 DH = 900 DH

**الحساب:**
- Total Cost = (10 × 50) + (20 × 55) + (15 × 60) = 500 + 1,100 + 900 = 2,500 DH
- Total Quantity = 10 + 20 + 15 = 45 قطعة
- **CMUP = 2,500 / 45 = 55.56 DH**

### مثال 2: مع حذف فاتورة

إذا قمنا بحذف الفاتورة الثانية (20 قطعة × 55 DH):

**المشتريات المتبقية:**
1. الفاتورة الأولى: 10 قطع × 50 DH = 500 DH
2. الفاتورة الثالثة: 15 قطعة × 60 DH = 900 DH

**الحساب الجديد:**
- Total Cost = 500 + 900 = 1,400 DH
- Total Quantity = 10 + 15 = 25 قطعة
- **CMUP = 1,400 / 25 = 56.00 DH**

## كيف يعمل النظام؟

### 1. عند إضافة فاتورة مشتريات جديدة

```php
// في PurchaseController::store()
foreach ($request->items as $item) {
    // إضافة تفاصيل المشتريات
    PurchaseDetail::create([...]);
    
    // تحديث المخزون
    $product->increment('current_stock_quantity', $item['quantity']);
}

// إعادة حساب CMUP لجميع المنتجات في الفاتورة
$cmupCalculator->updateCmupForProducts($affectedProductIds);
```

**مثال:**
- المنتج الحالي: CMUP = 50 DH، المخزون = 10 قطع
- فاتورة جديدة: 5 قطع × 60 DH
- بعد الحساب:
  - Total Cost = (10 × 50) + (5 × 60) = 500 + 300 = 800 DH
  - Total Quantity = 10 + 5 = 15 قطعة
  - **CMUP الجديد = 800 / 15 = 53.33 DH**

### 2. عند تحديث فاتورة

```php
// في PurchaseController::update()

// 1. إرجاع المخزون للقيم القديمة
foreach ($oldDetails as $oldDetail) {
    $product->decrement('current_stock_quantity', $oldDetail->quantity);
}

// 2. حذف التفاصيل القديمة
$purchase->details()->delete();

// 3. إضافة التفاصيل الجديدة
foreach ($request->items as $item) {
    PurchaseDetail::create([...]);
    $product->increment('current_stock_quantity', $item['quantity']);
}

// 4. إعادة حساب CMUP
$cmupCalculator->updateCmupForProducts($affectedProductIds);
```

**مثال:**
- المنتج: CMUP = 50 DH
- الفاتورة القديمة: 10 قطع × 50 DH (سيتم حذفها)
- الفاتورة الجديدة: 8 قطع × 55 DH (سيتم إضافتها)
- بعد الحساب:
  - جميع المشتريات السابقة (بدون الفاتورة القديمة) + الفاتورة الجديدة
  - **CMUP يعاد حسابه بناءً على جميع المشتريات النشطة**

### 3. عند حذف فاتورة

```php
// في PurchaseController::destroy()

// 1. إرجاع المخزون
foreach ($details as $detail) {
    $product->decrement('current_stock_quantity', $detail->quantity);
}

// 2. حذف التفاصيل (soft delete)
$purchase->details()->delete();

// 3. حذف الفاتورة (soft delete)
$purchase->delete();

// 4. إعادة حساب CMUP (سيستثني الفاتورة المحذوفة)
$cmupCalculator->updateCmupForProducts($affectedProductIds);
```

## خصائص مهمة

### 1. يستثني الفواتير المحذوفة

```php
$purchaseDetails = PurchaseDetail::join('purchase_invoices', ...)
    ->whereNull('purchase_details.deleted_at')  // تفاصيل غير محذوفة
    ->whereNull('purchase_invoices.deleted_at') // فواتير غير محذوفة
    ->get();
```

**المعنى:** إذا قمت بحذف فاتورة، سيتم استبعادها من حساب CMUP.

### 2. يعتمد على جميع المشتريات النشطة

ليس فقط المشتريات الأخيرة، بل **جميع** المشتريات النشطة (غير المحذوفة).

### 3. تلقائي عند أي تغيير

- عند إضافة فاتورة → إعادة حساب تلقائي
- عند تحديث فاتورة → إعادة حساب تلقائي
- عند حذف فاتورة → إعادة حساب تلقائي

## الأمر اليدوي

يمكنك إعادة حساب CMUP يدوياً باستخدام:

```bash
# لجميع المنتجات
php artisan cmup:recalculate

# لمنتج محدد
php artisan cmup:recalculate --product-id=1

# مع إعادة حساب المخزون أولاً
php artisan cmup:recalculate --recalculate-stock
```

## الفرق بين CMUP وسعر الشراء الأخير

- **سعر الشراء الأخير**: سعر آخر عملية شراء (قد يكون 60 DH)
- **CMUP**: متوسط مرجح لجميع المشتريات (قد يكون 55.56 DH)

CMUP يعطي صورة أكثر دقة عن تكلفة المنتج الإجمالية.

## ملاحظات مهمة

1. **CMUP يعتمد على المشتريات فقط**، وليس على المبيعات
2. **عند حذف فاتورة** (soft delete)، يتم استبعادها من الحساب
3. **CMUP يتغير** عند إضافة/تحديث/حذف أي فاتورة مشتريات
4. **الحساب دقيق** ويعتمد على جميع المشتريات الفعلية في قاعدة البيانات


