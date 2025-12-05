import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import { useQuasar } from 'quasar';

/**
 * Composable for generating invoice PDFs
 * @param {Object} options - Configuration options
 * @param {Function} options.onProgress - Callback for progress updates
 * @returns {Object} PDF generation functions
 */
export function useInvoicePDF(options = {}) {
  const $q = useQuasar();
  const { onProgress } = options;

  // PDF configuration constants
  const PDF_CONFIG = {
    pageWidth: 210, // A4 width in mm
    pageHeight: 297, // A4 height in mm
    marginTop: 20,
    marginBottom: 20,
    marginLeft: 15,
    marginRight: 15,
    footerHeight: 10,
    minRowsOnLastPage: 2,
    canvasScale: 1.5,
    renderDelay: 50,
  };

  /**
   * Generate PDF from HTML element
   * @param {HTMLElement} element - The HTML element to convert
   * @param {Object} config - PDF configuration
   * @param {string} fileName - Output file name
   * @returns {Promise<void>}
   */
  const generatePDF = async (element, config = {}, fileName = 'invoice.pdf') => {
    const finalConfig = { ...PDF_CONFIG, ...config };
    const {
      pageWidth,
      pageHeight,
      marginTop,
      marginBottom,
      marginLeft,
      marginRight,
      footerHeight,
      minRowsOnLastPage,
      canvasScale,
      renderDelay,
    } = finalConfig;

    const contentWidth = pageWidth - marginLeft - marginRight;
    const pdf = new jsPDF('p', 'mm', 'a4');
    let currentY = marginTop;
    let currentPage = 1;

    // Helper: Add page number
    const addPageNumber = (page, total) => {
      pdf.setFillColor(255, 255, 255);
      pdf.rect(pageWidth - marginRight - 35, pageHeight - 16, 35, 12, 'F');
      pdf.setFontSize(11);
      pdf.setTextColor(40, 40, 40);
      pdf.setFont('helvetica', 'bold');
      const pageText = `${page} / ${total}`;
      const textWidth = pdf.getTextWidth(pageText);
      pdf.text(pageText, pageWidth - marginRight - textWidth, pageHeight - 10);
    };

    // Helper: Capture element as image
    const captureElement = async (el, width) => {
      if (!el) return { img: null, height: 0 };
      const canvas = await html2canvas(el, {
        scale: canvasScale,
        useCORS: true,
        backgroundColor: '#ffffff',
        logging: false,
        allowTaint: false,
      });
      const img = canvas.toDataURL('image/png');
      const height = (canvas.height * width) / canvas.width;
      return { img, height };
    };

    // Get sections
    const headerSection = element.querySelector('.invoice-header-section');
    const infoSection = element.querySelector('.invoice-info-section');
    const transactionBar = element.querySelector('.transaction-bar');
    const itemsTable = element.querySelector('.invoice-table');
    const totalsSection = element.querySelector('.invoice-totals-section');
    const notesSection = element.querySelector('.invoice-notes-section');

    // Capture static sections
    if (onProgress) onProgress('Capturing header sections...');
    const { img: headerImg, height: headerHeight } = await captureElement(headerSection, contentWidth);
    const { img: infoImg, height: infoHeight } = await captureElement(infoSection, contentWidth);
    const { img: barImg, height: barHeight } = await captureElement(transactionBar, contentWidth);
    const { img: totalsImg, height: totalsHeight } = await captureElement(totalsSection, contentWidth);
    const { img: notesImg, height: notesHeight } = await captureElement(notesSection, contentWidth);

    // Add initial sections
    if (headerImg) {
      pdf.addImage(headerImg, 'PNG', marginLeft, currentY, contentWidth, headerHeight);
      currentY += headerHeight + 5;
    }
    if (infoImg) {
      pdf.addImage(infoImg, 'PNG', marginLeft, currentY, contentWidth, infoHeight);
      currentY += infoHeight + 5;
    }
    if (barImg) {
      pdf.addImage(barImg, 'PNG', marginLeft, currentY, contentWidth, barHeight);
      currentY += barHeight + 5;
    }

    // Process table with pagination
    if (itemsTable) {
      if (onProgress) onProgress('Processing table...');
      await processTable(
        itemsTable,
        pdf,
        {
          pageWidth,
          pageHeight,
          marginLeft,
          marginRight,
          marginTop,
          marginBottom,
          footerHeight,
          contentWidth,
          totalsHeight,
          minRowsOnLastPage,
          canvasScale,
          renderDelay,
          currentY,
          currentPage,
        }
      );
    }

    // Add totals section
    if (totalsSection && totalsImg) {
      if (onProgress) onProgress('Adding totals...');
      const totalPages = pdf.internal.getNumberOfPages();
      pdf.setPage(totalPages);
      const currentPageY = pdf.internal.pageSize.getHeight();
      const targetTotalsY = pageHeight - marginBottom - footerHeight - totalsHeight;
      pdf.addImage(totalsImg, 'PNG', marginLeft, targetTotalsY, contentWidth, totalsHeight);
    }

    // Add notes section
    if (notesSection && notesImg) {
      if (onProgress) onProgress('Adding notes...');
      const totalPages = pdf.internal.getNumberOfPages();
      pdf.setPage(totalPages);
      let currentPageY = pdf.internal.pageSize.getHeight();
      if (currentPageY + notesHeight > pageHeight - marginBottom - footerHeight) {
        pdf.addPage();
      }
      pdf.addImage(notesImg, 'PNG', marginLeft, currentPageY, contentWidth, notesHeight);
    }

    // Add page numbers to all pages
    if (onProgress) onProgress('Adding page numbers...');
    const totalPages = pdf.internal.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
      pdf.setPage(i);
      addPageNumber(i, totalPages);
    }

    // Save PDF
    pdf.save(fileName);
    if (onProgress) onProgress('Complete!');
  };

  /**
   * Process table with pagination logic
   * @private
   */
  const processTable = async (itemsTable, pdf, config) => {
    const {
      pageWidth,
      pageHeight,
      marginLeft,
      marginRight,
      marginTop,
      marginBottom,
      footerHeight,
      contentWidth,
      totalsHeight,
      minRowsOnLastPage,
      canvasScale,
      renderDelay,
      currentY: startY,
      currentPage: startPage,
    } = config;

    const tableRows = Array.from(itemsTable.querySelectorAll('tbody tr'));
    const tableHeader = itemsTable.querySelector('thead');

    if (tableRows.length === 0) return;

    const originalRowStyles = tableRows.map(row => row.style.display);
    if (tableHeader) tableHeader.style.display = '';

    const maxYForTable = pageHeight - marginBottom - footerHeight - totalsHeight - 10;
    let rowIndex = 0;
    const totalRows = tableRows.length;
    let currentY = startY;
    let currentPage = startPage;

    // Capture table portion helper
    const captureTablePortion = async (startRow, endRow, includeHeader = true) => {
      tableRows.forEach(row => row.style.display = 'none');
      
      if (includeHeader && tableHeader) {
        tableHeader.style.display = '';
      } else if (tableHeader) {
        tableHeader.style.display = 'none';
      }

      for (let i = startRow; i < endRow && i < totalRows; i++) {
        tableRows[i].style.display = '';
      }

      await new Promise(resolve => {
        itemsTable.offsetHeight;
        void itemsTable.offsetWidth;
        setTimeout(resolve, renderDelay);
      });

      const itemsSection = itemsTable.closest('.invoice-items-section');
      const containerToCapture = itemsSection || itemsTable;

      try {
        const canvas = await html2canvas(containerToCapture, {
          scale: canvasScale,
          useCORS: true,
          backgroundColor: '#ffffff',
          logging: false,
          allowTaint: false,
        });
        return canvas;
      } catch (error) {
        console.error('Error capturing table portion:', error);
        const canvas = await html2canvas(itemsTable, {
          scale: canvasScale,
          useCORS: true,
          backgroundColor: '#ffffff',
          logging: false,
          allowTaint: false,
        });
        return canvas;
      }
    };

    // Capture header once
    let headerImg = null;
    let headerHeight = 0;
    if (tableHeader) {
      tableRows.forEach(row => row.style.display = 'none');
      const headerCanvas = await html2canvas(tableHeader, {
        scale: canvasScale,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      headerImg = headerCanvas.toDataURL('image/png');
      headerHeight = (headerCanvas.height * contentWidth) / headerCanvas.width;
    }

    // Calculate actual row height
    let actualRowHeight = 0;
    const referenceRows = Math.min(15, totalRows);
    if (referenceRows > 0) {
      const firstPageCanvas = await captureTablePortion(0, referenceRows, true);
      const firstPageHeight = (firstPageCanvas.height * contentWidth) / firstPageCanvas.width;
      const rowsOnlyHeight = firstPageHeight - headerHeight;
      actualRowHeight = rowsOnlyHeight / referenceRows;
      if (actualRowHeight < 5 || actualRowHeight > 15) {
        actualRowHeight = headerHeight > 0 ? headerHeight * 0.7 : 8;
      }
    } else {
      actualRowHeight = headerHeight > 0 ? headerHeight * 0.7 : 8;
    }

    // Process rows page by page
    while (rowIndex < totalRows) {
      const remainingRows = totalRows - rowIndex;
      const needsNewPage = rowIndex > 0 && (currentY + headerHeight > maxYForTable);

      if (needsNewPage) {
        pdf.addPage();
        currentPage++;
        currentY = marginTop;
        if (headerImg && headerHeight > 0) {
          pdf.addImage(headerImg, 'PNG', marginLeft, currentY, contentWidth, headerHeight);
          currentY += headerHeight;
        }
      }

      // Determine space for totals
      let spaceForTotals = 0;
      const willCompleteTable = remainingRows <= minRowsOnLastPage + 1;

      if (rowIndex === 0) {
        if (totalRows <= 10) {
          spaceForTotals = totalsHeight + 5;
        } else if (totalRows >= 11 && totalRows <= 16) {
          spaceForTotals = 0;
        } else {
          spaceForTotals = 0;
        }
      } else if (willCompleteTable) {
        spaceForTotals = totalsHeight + 10;
      }

      const maxYForRows = pageHeight - marginBottom - footerHeight - spaceForTotals;
      const availableSpace = maxYForRows - currentY;

      // Estimate rows to fit
      let estimatedRowsToFit = Math.floor(availableSpace / actualRowHeight);
      estimatedRowsToFit = Math.max(1, Math.min(estimatedRowsToFit, remainingRows));

      // Apply first page logic
      if (rowIndex === 0) {
        if (totalRows <= 10) {
          estimatedRowsToFit = totalRows;
        } else if (totalRows >= 11 && totalRows <= 16) {
          estimatedRowsToFit = Math.min(totalRows - 1, remainingRows);
        } else {
          estimatedRowsToFit = Math.min(15, remainingRows);
        }
      } else {
        if (willCompleteTable && remainingRows > minRowsOnLastPage) {
          const maxRowsForCurrentPage = remainingRows - minRowsOnLastPage;
          estimatedRowsToFit = Math.min(estimatedRowsToFit, maxRowsForCurrentPage, 23);
          estimatedRowsToFit = Math.max(1, estimatedRowsToFit);
        } else {
          estimatedRowsToFit = Math.min(estimatedRowsToFit, 23, remainingRows);
        }
      }

      // Capture table portion
      const isFirstPageWithAllRows = (rowIndex === 0 && totalRows <= 10);
      let rowsGroupCanvas = await captureTablePortion(
        rowIndex,
        rowIndex + estimatedRowsToFit,
        rowIndex === 0
      );

      let rowsHeight = (rowsGroupCanvas.height * contentWidth) / rowsGroupCanvas.width;
      const isLastPortion = (rowIndex + estimatedRowsToFit) >= totalRows;

      // Adjust if doesn't fit (unless first page with all rows)
      if (rowsHeight > availableSpace && estimatedRowsToFit > 1 && !isFirstPageWithAllRows) {
        estimatedRowsToFit--;
        rowsGroupCanvas = await captureTablePortion(
          rowIndex,
          rowIndex + estimatedRowsToFit,
          rowIndex === 0
        );
        rowsHeight = (rowsGroupCanvas.height * contentWidth) / rowsGroupCanvas.width;
      }

      // Add rows to PDF
      pdf.addImage(
        rowsGroupCanvas.toDataURL('image/png'),
        'PNG',
        marginLeft,
        currentY,
        contentWidth,
        rowsHeight
      );
      currentY += rowsHeight;

      // Add spacing on last page
      if (isLastPortion) {
        const targetTotalsY = pageHeight - marginBottom - footerHeight - totalsHeight;
        const availableSpaceForTotals = targetTotalsY - currentY;
        if (availableSpaceForTotals > 10) {
          const spacing = availableSpaceForTotals - 5;
          currentY += spacing;
        }
      }

      rowIndex += estimatedRowsToFit;
    }

    // Restore original styles
    tableRows.forEach((row, index) => {
      row.style.display = originalRowStyles[index] || '';
    });
    if (tableHeader) tableHeader.style.display = '';
  };

  return {
    generatePDF,
    PDF_CONFIG,
  };
}

