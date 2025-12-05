/**
 * Utility functions for invoice operations
 */

/**
 * Format date to DD/MM/YYYY
 * @param {string|Date} dateString - Date string or Date object
 * @returns {string} Formatted date string
 */
export const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
};

/**
 * Convert date from ISO format to yyyy-MM-dd format for date input fields
 * @param {string|Date} dateString - Date string (ISO format) or Date object
 * @returns {string} Date string in yyyy-MM-dd format
 */
export const formatDateForInput = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return '';
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

/**
 * Calculate subtotal from invoice details
 * @param {Array} details - Invoice details array
 * @returns {number} Subtotal amount
 */
export const calculateSubtotal = (details) => {
  if (!details || !Array.isArray(details)) return 0;
  const subtotal = details.reduce((sum, item) => {
    const quantity = parseFloat(item.quantity) || 0;
    const price = parseFloat(item.purchase_price) || 0;
    return sum + (quantity * price);
  }, 0);
  return isNaN(subtotal) ? 0 : subtotal;
};

/**
 * Get total amount from invoice (prefers total_in_invoice over total_amount)
 * @param {Object} invoice - Invoice object
 * @returns {number} Total amount
 */
export const getTotalAmount = (invoice) => {
  if (!invoice) return 0;
  const total = invoice.total_in_invoice || invoice.total_amount || 0;
  return parseFloat(total) || 0;
};

/**
 * Get company logo URL
 * @param {string} logoPath - Logo path from settings
 * @param {string} baseUrl - Base URL for assets (default: localhost:8000)
 * @returns {string} Full logo URL
 */
export const getLogoUrl = (logoPath, baseUrl = 'http://localhost:8000') => {
  if (!logoPath) return '';
  if (logoPath.startsWith('http')) return logoPath;
  return `${baseUrl}/storage/${logoPath}`;
};

/**
 * Format currency amount
 * @param {number} amount - Amount to format
 * @param {string} currency - Currency symbol (default: 'DH')
 * @param {number} decimals - Number of decimals (default: 2)
 * @returns {string} Formatted currency string
 */
export const formatCurrency = (amount, currency = 'DH', decimals = 2) => {
  const num = parseFloat(amount) || 0;
  return `${num.toFixed(decimals)} ${currency}`;
};

/**
 * Generate invoice filename
 * @param {string} invoiceNumber - Invoice number
 * @param {string} prefix - File prefix (default: 'invoice')
 * @param {Date} date - Date for filename (default: today)
 * @returns {string} Generated filename
 */
export const generateInvoiceFileName = (invoiceNumber, prefix = 'invoice', date = new Date()) => {
  const dateStr = date.toISOString().split('T')[0];
  const safeInvoiceNumber = invoiceNumber || 'invoice';
  return `${prefix}_${safeInvoiceNumber}_${dateStr}.pdf`;
};

