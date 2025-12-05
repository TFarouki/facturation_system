# Invoice Template & PDF Generation - دليل الاستخدام

هذا الدليل يشرح كيفية استخدام مكونات الفاتورة القابلة لإعادة الاستخدام.

## 📁 البنية الجديدة (New Structure)

```
resources/js/
├── composables/
│   └── useInvoicePDF.js       # Composable لإنشاء PDF
├── utils/
│   └── invoiceUtils.js        # دوال مساعدة للفاتورة
├── styles/
│   └── invoice.css            # تصميم الفاتورة
└── components/
    └── InvoiceTemplate.vue    # مكون قالب الفاتورة (يمكن إنشاؤه لاحقاً)
```

## 🚀 الاستخدام (Usage)

### 1. استخدام Composable لإنشاء PDF

```javascript
import { useInvoicePDF } from '@/composables/useInvoicePDF';

const { generatePDF } = useInvoicePDF({
  onProgress: (message) => {
    console.log(message); // 'Generating PDF...', 'Processing table...', etc.
  }
});

// استخدام الدالة
const handleGeneratePDF = async () => {
  const invoiceElement = document.getElementById('invoice-content');
  const fileName = 'invoice_2025000001_2025-01-01.pdf';
  
  await generatePDF(invoiceElement, {}, fileName);
};
```

### 2. استخدام الدوال المساعدة (Utils)

```javascript
import { 
  formatDate, 
  calculateSubtotal, 
  getTotalAmount,
  formatCurrency,
  generateInvoiceFileName 
} from '@/utils/invoiceUtils';

// تنسيق التاريخ
const formattedDate = formatDate('2025-01-15'); // "15/01/2025"

// حساب المجموع الفرعي
const subtotal = calculateSubtotal(invoiceDetails);

// الحصول على المجموع الكلي
const total = getTotalAmount(invoice);

// تنسيق العملة
const price = formatCurrency(1250.50); // "1250.50 DH"

// إنشاء اسم الملف
const fileName = generateInvoiceFileName('INV-001');
```

### 3. استخدام CSS

في ملف Vue الخاص بك:

```vue
<style scoped>
@import '@/styles/invoice.css';
</style>
```

أو بشكل عام:

```vue
<style>
@import '@/styles/invoice.css';
</style>
```

## 📝 مثال كامل (Full Example)

```vue
<template>
  <div>
    <div id="invoice-content" class="invoice-content">
      <!-- محتوى الفاتورة -->
    </div>
    <button @click="saveAsPDF">حفظ PDF</button>
  </div>
</template>

<script setup>
import { useInvoicePDF } from '@/composables/useInvoicePDF';
import { generateInvoiceFileName } from '@/utils/invoiceUtils';
import { useQuasar } from 'quasar';

const $q = useQuasar();
const { generatePDF } = useInvoicePDF({
  onProgress: (message) => {
    $q.notify({ type: 'info', message, timeout: 1000 });
  }
});

const saveAsPDF = async () => {
  try {
    const invoiceElement = document.getElementById('invoice-content');
    const fileName = generateInvoiceFileName(invoice.value.invoice_number);
    
    await generatePDF(invoiceElement, {}, fileName);
    
    $q.notify({ 
      type: 'positive', 
      message: 'تم حفظ PDF بنجاح' 
    });
  } catch (error) {
    $q.notify({ 
      type: 'negative', 
      message: 'فشل في إنشاء PDF' 
    });
  }
};
</script>

<style>
@import '@/styles/invoice.css';
</style>
```

## ⚙️ التخصيص (Customization)

### تخصيص إعدادات PDF

```javascript
const customConfig = {
  pageWidth: 210,
  pageHeight: 297,
  marginTop: 25,
  marginBottom: 25,
  // ... إعدادات أخرى
};

await generatePDF(element, customConfig, fileName);
```

### تخصيص CSS

يمكنك تخصيص الألوان والتصميم عن طريق override في ملف CSS الخاص بك:

```css
.invoice-table thead {
  background: your-custom-gradient !important;
}
```

## 🔄 إعادة الاستخدام في صفحات أخرى

لإستخدام الفاتورة في صفحة أخرى (مثل Sales Invoices):

1. استيراد Composables والUtils
2. استيراد CSS
3. استخدام نفس HTML structure مع class names المحددة
4. استدعاء `generatePDF` بنفس الطريقة

## 📦 المزايا (Benefits)

✅ **منفصل**: كل وظيفة في ملفها الخاص  
✅ **قابل لإعادة الاستخدام**: يمكن استخدامه في أي مكان  
✅ **سهل الصيانة**: تعديل واحد يطبق على كل الاستخدامات  
✅ **سهل الاختبار**: كل دالة قابلة للاختبار بشكل منفصل  
✅ **مستند**: كل ملف موثق بالتعليقات

## 🔧 التحسينات المستقبلية (Future Improvements)

- [ ] إنشاء `InvoiceTemplate.vue` component
- [ ] إضافة دعم للغات متعددة
- [ ] إضافة templates مختلفة للفاتورة
- [ ] إضافة دعم للتوقيع الرقمي
- [ ] تحسين الأداء (caching, lazy loading)

