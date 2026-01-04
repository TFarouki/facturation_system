<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">{{ $t('purchases.title') }}</div>
      <div class="row q-gutter-sm">
        <q-input
          v-model="searchText"
          outlined
          dense
          :placeholder="$t('common.search') + '...'"
          style="min-width: 250px"
        >
          <template v-slot:prepend>
            <q-icon name="search" />
          </template>
        </q-input>
        <q-btn color="secondary" icon="download" flat dense @click="exportToExcel">
          <q-tooltip>{{ $t('common.download') }}</q-tooltip>
        </q-btn>
        <q-btn color="grey-7" icon="more_vert" flat dense>
          <q-tooltip>{{ $t('common.actions') }}</q-tooltip>
          <q-menu>
            <q-list style="min-width: 200px">
              <q-item clickable v-close-popup @click="toggleFilterMismatch">
                <q-item-section avatar>
                  <q-icon :name="filterMismatchOnly ? 'check_box' : 'check_box_outline_blank'" color="primary" />
                </q-item-section>
                <q-item-section>{{ $t('purchases.filterMismatch') }}</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
        <q-btn color="primary" icon="add" :label="$t('purchases.newPurchase')" @click="showDialog = true" />
      </div>
    </div>

    <q-table
      :rows="filteredInvoices"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
      :rows-per-page-label="$t('common.rowsPerPage')"
      :no-data-label="$t('common.noData')"
      :loading-label="$t('common.loading')"
      v-model:pagination="pagination"
    >
      <template v-slot:body-cell-supplier="props">
        <q-td :props="props">
          {{ props.row.supplier?.name || 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-date="props">
        <q-td :props="props">
          {{ props.row.invoice_date ? formatDate(props.row.invoice_date) : 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-total_calculated="props">
        <q-td :props="props">
          {{ props.row.total_amount ? parseFloat(props.row.total_amount).toFixed(2) + ' DH' : 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-total="props">
        <q-td :props="props">
          {{ (props.row.total_in_invoice || props.row.total_amount) ? parseFloat(props.row.total_in_invoice || props.row.total_amount).toFixed(2) + ' DH' : 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-items_count="props">
        <q-td :props="props">
          {{ props.row.details_count || 0 }}
        </q-td>
      </template>
      <template v-slot:body-cell-image="props">
        <q-td :props="props">
          <q-btn 
            v-if="props.row.invoice_image_path" 
            round 
            dense 
            :icon="getFileIcon(props.row.invoice_image_path)" 
            :color="getFileColor(props.row.invoice_image_path)"
            size="sm"
            @click="viewImage(props.row.invoice_image_path)"
          >
            <q-tooltip>{{ getFileTooltip(props.row.invoice_image_path) }}</q-tooltip>
          </q-btn>
          <span v-else class="text-grey-5">-</span>
        </q-td>
      </template>
        <template v-slot:body-cell-payment_status="props">
          <q-td :props="props">
            <q-badge 
              :color="getPaymentInfo(props.row).color" 
              class="q-pa-xs cursor-pointer"
            >
              <q-icon :name="getPaymentInfo(props.row).icon" size="xs" class="q-mr-xs" />
              {{ getPaymentInfo(props.row).label }}
              <q-tooltip v-if="getPaymentInfo(props.row).tooltip">
                {{ getPaymentInfo(props.row).tooltip }}
              </q-tooltip>
            </q-badge>
          </q-td>
        </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <div class="row items-center q-gutter-xs">   
            <q-btn flat dense icon="visibility" color="positive" @click="viewDetails(props.row)">
              <q-tooltip>{{ $t('common.view') }}</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="edit" color="primary" @click="editInvoice(props.row)">
              <q-tooltip>{{ $t('common.edit') }}</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="delete" color="negative" @click="confirmDelete(props.row)">
              <q-tooltip>{{ $t('common.delete') }}</q-tooltip>
            </q-btn>
            <q-btn 
              flat 
              dense 
              icon="attach_file" 
              color="primary" 
              @click="openAttachFileDialog(props.row)"
            >
              <q-tooltip>{{ $t('purchases.attachDocument') }}</q-tooltip>
            </q-btn>
            <q-icon 
              v-if="props.row.it_has_def" 
              name="warning" 
              color="warning" 
              size="md"
            >
              <q-tooltip>{{ $t('purchases.totalMismatch') }}</q-tooltip>
            </q-icon>
            <q-btn flat dense icon="attach_money" color="positive" @click="openPaymentDialog(props.row)">
              <q-tooltip>{{ $t('payments.title') }}</q-tooltip>
            </q-btn>
          </div>
        </q-td>
      </template>
    </q-table>

    <!-- Add Purchase Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 700px; max-width: 95vw;">
        <!-- Header with Warning -->
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ $t('purchases.newPurchase') }}</div>
        </q-card-section>


        <q-card-section class="q-pa-md">
          <q-form @submit.prevent="handleSaveAttempt">
            <!-- Section 1: Invoice Information -->
            <div class="section-header compact">
              <q-icon name="receipt" size="sm" class="q-mr-xs" />
              <span class="text-subtitle2 text-weight-bold">{{ $t('purchases.invoiceInfo') }}</span>
            </div>
            <q-separator class="q-mb-xs" />
            
            <div class="row q-col-gutter-xs q-mb-md">
              <div class="col-4">
                <q-select
                  v-model="form.supplier_id"
                  :options="supplierOptions"
                  option-value="value"
                  option-label="label"
                  :label="$t('purchases.supplier') + ' *'"
                  outlined
                  dense
                  emit-value
                  map-options
                  use-input
                  @filter="filterSuppliers"
                  :rules="[val => !!val || $t('messages.required')]"
                >
                  <template v-slot:prepend>
                    <q-icon name="business" size="xs" />
                  </template>
                  <template v-slot:no-option>
                    <q-item clickable @click="openSupplierDialog">
                      <q-item-section class="text-primary">
                        <q-icon name="add" /> {{ $t('purchases.addNewSupplier') }}
                      </q-item-section>
                    </q-item>
                  </template>
                </q-select>
              </div>
              <div class="col-3">
                <q-input 
                  v-model="form.invoice_number" 
                  :label="$t('purchases.invoiceNumber') + ' *'" 
                  outlined 
                  dense 
                  :rules="[val => !!val || $t('messages.required')]"
                >
                  <template v-slot:prepend>
                    <q-icon name="tag" size="xs" />
                  </template>
                </q-input>
              </div>
              <div class="col-2-5">
                <q-input 
                  v-model="form.invoice_date" 
                  :label="$t('common.date') + ' *'" 
                  type="date" 
                  outlined 
                  dense 
                  :rules="[val => !!val || $t('messages.required')]"
                >
                  <template v-slot:prepend>
                    <q-icon name="event" size="xs" />
                  </template>
                </q-input>
              </div>
              <div class="col-2-5">
                <q-input
                  v-model.number="form.manual_total"
                  :label="$t('common.total') + ' *'"
                  type="number"
                  outlined
                  dense
                  step="0.01"
                  suffix="DH"
                  :rules="[val => val > 0 || $t('messages.required')]"
                >
                  <template v-slot:prepend>
                    <q-icon name="payments" size="xs" />
                  </template>
                </q-input>
              </div>
            </div>

            <!-- Section 2: Products List -->
            <div class="section-header">
              <q-icon name="inventory_2" size="sm" class="q-mr-sm" />
              <span class="text-subtitle1 text-weight-bold">{{ $t('purchases.products') }}</span>
            </div>
            <q-separator class="q-mb-md" />
            
            <div class="products-section q-mb-lg">
              <div v-for="(item, index) in form.items" :key="index" class="row q-col-gutter-sm q-mb-sm">
                <div class="col-4">
                  <q-select
                    v-model="item.product_id"
                    :options="productOptions"
                    option-value="value"
                    option-label="label"
                    :label="$t('purchases.product')"
                    outlined
                    dense
                    emit-value
                    map-options
                    use-input
                    @filter="filterProducts"
                  >
                    <template v-slot:no-option>
                      <q-item clickable @click="openProductDialog">
                        <q-item-section class="text-primary">
                          <q-icon name="add" /> {{ $t('purchases.addNewProduct') }}
                        </q-item-section>
                      </q-item>
                    </template>
                  </q-select>
                </div>
                <div class="col-3">
                  <q-input 
                    v-model.number="item.quantity" 
                    :label="$t('sales.quantity')" 
                    type="number" 
                    outlined 
                    dense 
                    min="0"
                    step="0.01"
                  />
                </div>
                <div class="col-3">
                  <q-input 
                    v-model.number="item.purchase_price" 
                    :label="$t('purchases.price') + ' (DH)'" 
                    type="number" 
                    outlined 
                    dense 
                    step="0.01"
                    min="0"
                  />
                </div>
                <div class="col-2 flex items-center justify-center">
                  <q-btn 
                    flat 
                    dense 
                    round
                    icon="delete" 
                    color="negative" 
                    @click="removeItem(index)"
                    :disable="form.items.length === 1"
                  >
                    <q-tooltip>{{ $t('purchases.removeItem') }}</q-tooltip>
                  </q-btn>
                </div>
              </div>

              <div class="row q-mt-sm">
                <q-btn 
                  flat 
                  color="primary" 
                  icon="add" 
                  :label="$t('purchases.addItem')" 
                  @click="addItem" 
                  size="sm"
                />
              </div>
            </div>

            <!-- Section 3: Total & Notes -->
            <div class="section-header compact">
              <q-icon name="calculate" size="sm" class="q-mr-xs" />
              <span class="text-subtitle2 text-weight-bold">{{ $t('purchases.calculatedTotalNotes') }}</span>
            </div>
            <q-separator class="q-mb-xs" />
            
            <div class="row q-col-gutter-xs">
              <div class="col-3">
                <div class="q-pa-xs bg-grey-2 rounded-borders" style="height: 100%;">
                  <div class="text-caption text-grey-7">{{ $t('purchases.calculatedTotal') }}</div>
                  <div class="text-h6 text-weight-bold">
                    {{ calculatedTotal.toFixed(2) }} DH
                  </div>
                </div>
              </div>
              <div class="col-9">
                <q-input 
                  v-model="form.notes" 
                  :label="$t('common.notes')" 
                  outlined 
                  dense 
                  type="textarea" 
                  rows="2"
                >
                  <template v-slot:prepend>
                    <q-icon name="notes" size="xs" />
                  </template>
                </q-input>
              </div>
            </div>

            <!-- Mismatch handling toggle -->
            <div class="row q-mt-md q-mb-sm">
              <q-toggle
                v-model="allowMismatch"
                :label="$t('purchases.saveDespiteDifference')"
                color="warning"
                dense
              />
            </div>

            <!-- Action Buttons -->
            <div class="row justify-end q-gutter-sm q-mt-md">
              <q-btn :label="$t('common.cancel')" flat @click="closeDialog" />
              <q-btn 
                type="submit" 
                :label="$t('common.save')" 
                color="primary" 
                :loading="saving"
                icon="save"
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Quick Add Product Dialog -->
    <q-dialog v-model="showProductDialog">
      <q-card style="min-width: 500px">
        <q-card-section class="bg-secondary text-white">
          <div class="text-h6">{{ $t('purchases.quickAddProduct') }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveQuickProduct">
            <q-input v-model="productForm.name" :label="$t('purchases.productName') + ' *'" outlined dense class="q-mb-md" :rules="[val => !!val || $t('messages.required')]" />
            <q-input v-model="productForm.product_code" :label="$t('purchases.productCode')" outlined dense class="q-mb-md" />
            <q-input v-model="productForm.barcode" :label="$t('products.barcode')" outlined dense class="q-mb-md" />
            <category-select v-model="productForm.category_id" :label="$t('products.category')" />
            <q-input v-model="productForm.unit_of_measure" :label="$t('products.unit')" outlined dense class="q-mb-md" />
            <q-input v-model.number="productForm.current_stock_quantity" :label="$t('purchases.initialStock')" type="number" outlined dense class="q-mb-md" />
            
            <div class="text-subtitle2 q-mb-sm">{{ $t('purchases.pricing') }}</div>
            <div class="row q-col-gutter-sm q-mb-md">
              <div class="col-3">
                <q-input v-model.number="productForm.wholesale_price" :label="$t('sales.wholesale')" type="number" outlined dense step="0.01" />
              </div>
              <div class="col-3">
                <q-input v-model.number="productForm.semi_wholesale_price" :label="$t('sales.semiWholesale')" type="number" outlined dense step="0.01" />
              </div>
              <div class="col-3">
                <q-input v-model.number="productForm.retail_price" :label="$t('sales.retail')" type="number" outlined dense step="0.01" />
              </div>
              <div class="col-3">
                <TaxSelect v-model="productForm.tax_rate" :label="$t('products.taxRate')" />
              </div>
            </div>

            <div class="row justify-end q-gutter-sm">
              <q-btn :label="$t('common.cancel')" flat @click="showProductDialog = false" />
              <q-btn type="submit" :label="$t('common.save')" color="secondary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Quick Add Supplier Dialog -->
    <q-dialog v-model="showSupplierDialog">
      <q-card style="min-width: 500px">
        <q-card-section class="bg-secondary text-white">
          <div class="text-h6">{{ $t('purchases.quickAddSupplier') }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveQuickSupplier">
            <q-input v-model="supplierForm.name" :label="$t('purchases.supplierName') + ' *'" outlined dense class="q-mb-md" :rules="[val => !!val || $t('messages.required')]" />
            <q-input v-model="supplierForm.contact_person" :label="$t('purchases.contactPerson')" outlined dense class="q-mb-md" />
            <q-input v-model="supplierForm.phone" :label="$t('suppliers.phone')" outlined dense class="q-mb-md" />
            <q-input v-model="supplierForm.email" :label="$t('suppliers.email')" type="email" outlined dense class="q-mb-md" />
            <q-input v-model="supplierForm.address" :label="$t('suppliers.address')" outlined dense type="textarea" rows="2" class="q-mb-md" />
            <q-input v-model="supplierForm.tax_id" :label="$t('suppliers.taxId')" outlined dense class="q-mb-md" />

            <div class="row justify-end q-gutter-sm">
              <q-btn :label="$t('common.cancel')" flat @click="showSupplierDialog = false" />
              <q-btn type="submit" :label="$t('common.save')" color="secondary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Price Update Dialog -->
    <q-dialog v-model="showPriceDialog">
      <q-card style="min-width: 500px">
        <q-card-section class="bg-warning text-white">
          <div class="text-h6">{{ $t('products.updatePrices') }}</div>
        </q-card-section>

        <q-card-section>
          <div v-for="update in priceUpdates" :key="update.product_id" class="q-mb-md">
            <div class="text-subtitle1">{{ update.product_name }}</div>
            <div class="text-caption">{{ $t('purchases.newPurchasePrice') }}: {{ update.new_purchase_cost }} DH</div>
            <div class="row q-col-gutter-sm q-mt-sm">
              <div class="col-4">
                <q-input 
                  v-model.number="update.wholesale_price" 
                  :label="$t('sales.wholesale')" 
                  type="number" 
                  outlined 
                  dense 
                  step="0.01" 
                  @update:model-value="val => calculateUpdatePrices(val, update)"
                />
              </div>
              <div class="col-4">
                <q-input v-model.number="update.semi_wholesale_price" :label="$t('sales.semiWholesale')" type="number" outlined dense step="0.01" />
              </div>
              <div class="col-4">
                <q-input v-model.number="update.retail_price" :label="$t('sales.retail')" type="number" outlined dense step="0.01" />
              </div>
            </div>
          </div>

          <div class="row justify-end q-gutter-sm q-mt-md">
            <q-btn :label="$t('common.skip')" flat @click="showPriceDialog = false" />
            <q-btn :label="$t('products.updatePrices')" color="warning" @click="updatePrices" :loading="saving" />
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Invoice Print View Dialog -->
    <q-dialog v-model="showDetailsDialog" maximized>
      <q-card class="invoice-card" style="overflow-y: auto;">
        <q-card-section class="invoice-header q-pa-none">
          <div class="row items-center justify-between q-pa-md">
            <q-btn flat dense icon="close" @click="showDetailsDialog = false" />
            <div class="text-h6">{{ $t('purchases.purchaseInvoice') }}</div>
            <q-btn 
              flat 
              dense 
              icon="picture_as_pdf" 
              :label="$t('sales.saveAsPdf')" 
              color="primary"
              @click="saveAsPDF"
              :loading="pdfLoading"
            />
          </div>
        </q-card-section>

        <q-card-section v-if="selectedInvoice" class="invoice-content" id="invoice-content">
          <!-- Invoice Header -->
          <div class="invoice-header-section">
            <div class="row items-start justify-between">
              <!-- Left: Date and Invoice Number -->
              <div class="col-3">
                <div class="invoice-label q-mb-xs">{{ $t('common.date').toUpperCase() }}</div>
                <div class="invoice-value">{{ formatDate(selectedInvoice.invoice_date) }}</div>
                <div class="invoice-label q-mt-md q-mb-xs">{{ $t('purchases.invoiceNumber').toUpperCase() }}</div>
                <div class="invoice-value">{{ selectedInvoice.invoice_number }}</div>
              </div>

              <!-- Center: Company Name -->
              <div class="col-5 text-center">
                <div class="text-h4 text-weight-bold q-mb-md">{{ $t('purchases.purchaseInvoice').toUpperCase() }}</div>
                <div class="company-name text-weight-bold">{{ companySettings.company_name || 'YOUR COMPANY' }}</div>
              </div>

              <!-- Right: Logo -->
              <div class="col-3 text-right">
                <div v-if="companySettings.company_logo" class="company-logo q-mb-md">
                  <img :src="getLogoUrl(companySettings.company_logo)" alt="Company Logo" style="max-width: 100px; max-height: 100px;" />
                </div>
                <div v-else class="logo-placeholder q-mb-md">
                  <q-icon name="business" size="80px" color="grey-4" />
                  <div class="text-caption text-grey-6">{{ $t('purchases.logoName') }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Company and Supplier Info -->
          <div class="invoice-info-section">
            <div class="row q-col-gutter-md">
              <!-- Company Info -->
              <div class="col-6">
                <div class="invoice-label q-mb-xs">{{ $t('settings.companyInfo').toUpperCase() }}</div>
                <div class="invoice-info">
                  <div v-if="companySettings.address">{{ companySettings.address }}</div>
                  <div v-if="companySettings.phone">{{ $t('settings.phone') }}: {{ companySettings.phone }}</div>
                  <div v-if="companySettings.email">{{ $t('settings.email') }}: {{ companySettings.email }}</div>
                </div>
              </div>

              <!-- Supplier Info -->
              <div class="col-6 text-right">
                <div class="invoice-label q-mb-xs">{{ $t('purchases.supplier').toUpperCase() }}</div>
                <div class="invoice-info">
                  <div class="text-weight-bold">{{ selectedInvoice.supplier?.name || selectedInvoice.supplier_name || 'N/A' }}</div>
                  <div v-if="selectedInvoice.supplier?.address">{{ selectedInvoice.supplier.address }}</div>
                  <div v-if="selectedInvoice.supplier?.phone">{{ $t('settings.phone') }}: {{ selectedInvoice.supplier.phone }}</div>
                  <div v-if="selectedInvoice.supplier?.email">{{ $t('settings.email') }}: {{ selectedInvoice.supplier.email }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Transaction Details Bar -->
          <div class="transaction-bar">
            <div class="row">
              <div class="col-3">
                <div class="transaction-label">{{ $t('purchases.declaredTotal').toUpperCase() }}</div>
                <div class="transaction-value">{{ selectedInvoice.total_in_invoice || '0.00' }}</div>
              </div>
              <div class="col-3">
                <div class="transaction-label">{{ $t('purchases.invoiceDate').toUpperCase() }}</div>
                <div class="transaction-value">{{ formatDate(selectedInvoice.invoice_date) }}</div>
              </div>
            </div>
          </div>

          <!-- Items Table -->
          <div class="invoice-items-section" v-if="selectedInvoice.details && selectedInvoice.details.length > 0">
            <table class="invoice-table">
              <thead>
                <tr>
                  <th style="width: 5%">#</th>
                  <th style="width: 45%">{{ $t('products.description').toUpperCase() }}</th>
                  <th style="width: 10%">{{ $t('sales.quantity').toUpperCase() }}</th>
                  <th style="width: 20%" class="text-right">{{ $t('sales.unitPrice').toUpperCase() }}</th>
                  <th style="width: 20%" class="text-right">{{ $t('sales.lineTotal').toUpperCase() }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in selectedInvoice.details" :key="index">
                  <td class="text-center">{{ index + 1 }}</td>
                  <td>
                    <div class="text-weight-medium">{{ item.product?.name || 'Product' }}</div>
                    <div class="text-caption text-grey-6" v-if="item.product?.product_description">
                      {{ item.product.product_description }}
                    </div>
                  </td>
                  <td>{{ (parseFloat(item.quantity) || 0).toFixed(2) }}</td>
                  <td class="text-right">{{ (parseFloat(item.purchase_price) || 0).toFixed(2) }} DH</td>
                  <td class="text-right">{{ ((parseFloat(item.quantity) || 0) * (parseFloat(item.purchase_price) || 0)).toFixed(2) }} DH</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-center q-pa-md text-grey-6">
            {{ $t('purchases.noItemsInInvoice') }}
          </div>

          <!-- Totals Section -->
          <div class="invoice-totals-section">
            <div class="row justify-end">
              <div class="totals-box">
                <div class="total-row">
                  <span class="total-label">{{ $t('sales.subtotal') }}</span>
                  <span class="total-value">{{ (calculateSubtotal() || 0).toFixed(2) }} DH</span>
                </div>
                <div class="total-row">
                  <span class="total-label">{{ $t('common.total') }}</span>
                  <span class="total-value final">{{ getTotalAmount().toFixed(2) }} DH</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes Section -->
          <div class="invoice-notes-section" v-if="selectedInvoice.notes">
            <div class="invoice-label q-mb-xs">{{ $t('common.notes').toUpperCase() }}</div>
            <div class="invoice-notes">{{ selectedInvoice.notes }}</div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="invoice-actions">
          <q-btn :label="$t('common.close')" flat @click="showDetailsDialog = false" />
          <q-btn 
            :label="$t('sales.saveAsPdf')" 
            icon="picture_as_pdf" 
            color="primary"
            @click="saveAsPDF"
            :loading="pdfLoading"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Attach File Dialog -->
    <q-dialog v-model="showAttachDialog">
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">{{ $t('purchases.attachInvoiceDocTitle') }}</div>
          <div class="text-caption text-grey-7">
            Invoice #{{ selectedInvoiceForAttach?.invoice_number }}
          </div>
        </q-card-section>

        <q-card-section>
          <q-file
            v-model="attachedFile"
            outlined
            :label="$t('purchases.selectFile')"
            accept="image/*,application/pdf"
            max-file-size="5242880"
            @rejected="onFileRejected"
          >
            <template v-slot:prepend>
              <q-icon name="attach_file" />
            </template>
          </q-file>
          <div class="text-caption text-grey-7 q-mt-sm">
            {{ $t('purchases.maxFileSize') }}
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn :label="$t('common.cancel')" flat @click="closeAttachDialog" />
          <q-btn 
            :label="$t('purchases.upload')" 
            color="primary" 
            :disable="!attachedFile"
            :loading="saving"
            @click="uploadInvoiceFile" 
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
    <!-- Payment History Dialog -->
    <q-dialog v-model="showPaymentDialog">
      <q-card style="min-width: 600px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ $t('purchases.paymentHistory') }}</div>
          <div class="text-subtitle2" v-if="selectedInvoiceForPayment">
            Invoice #{{ selectedInvoiceForPayment.invoice_number }} - {{ selectedInvoiceForPayment.supplier?.name }}
          </div>
        </q-card-section>

        <q-card-section class="q-pa-md">
          <div class="row q-col-gutter-md q-mb-lg">
             <div class="col-4">
                <div class="text-caption text-grey">{{ $t('payments.totalInvoice') }}</div>
                <div class="text-h6">{{ (parseFloat(selectedInvoiceForPayment?.total_in_invoice || selectedInvoiceForPayment?.total_amount || 0)).toFixed(2) }} DH</div>
             </div>
             <div class="col-4">
                <div class="text-caption text-grey">{{ $t('payments.totalPaid') }}</div>
                <div class="text-h6 text-positive">{{ totalPaid.toFixed(2) }} DH</div>
             </div>
             <div class="col-4">
                <div class="text-caption text-grey">{{ $t('payments.remaining') }}</div>
                <div class="text-h6 text-negative">{{ remainingBalance.toFixed(2) }} DH</div>
             </div>
          </div>

          <!-- Add Payment Form -->
          <q-card bordered flat class="q-mb-md">
            <q-card-section>
              <div class="text-subtitle2 q-mb-sm">{{ $t('payments.addNewPayment') }}</div>
              <q-form @submit.prevent="savePayment">
                <div class="row q-col-gutter-sm">
                  <div class="col-4">
                    <q-input v-model.number="paymentForm.amount" :label="$t('payments.amount') + ' *'" type="number" outlined dense step="0.01">
                       <template v-slot:append>
                           <q-btn flat dense color="primary" :label="$t('payments.payAll')" size="sm" @click="paymentForm.amount = remainingBalance" />
                       </template>
                    </q-input>
                  </div>
                  <div class="col-4">
                    <q-input v-model="paymentForm.payment_date" :label="$t('common.date') + ' *'" type="date" outlined dense />
                  </div>
                  <div class="col-4">
                    <q-select 
                        v-model="paymentForm.payment_method" 
                        :options="paymentMethods" 
                        emit-value 
                        map-options 
                        :label="$t('payments.paymentMethod') + ' *'" 
                        outlined 
                        dense 
                        option-value="value"
                        option-label="label"
                    />
                  </div>
                  <div class="col-4">
                    <q-input 
                        v-model="paymentForm.reference" 
                        :label="paymentForm.payment_method === 'check' ? $t('payments.checkNo') : (paymentForm.payment_method === 'bank_transfer' ? $t('payments.refNo') : $t('payments.receiptNo'))" 
                        outlined 
                        dense 
                    />
                  </div>
                  <div class="col-4" v-if="paymentForm.payment_method === 'check'">
                    <q-input v-model="paymentForm.check_date" :label="$t('payments.checkDate')" type="date" outlined dense />
                  </div>
                  <div class="col-4">
                    <q-input v-model="paymentForm.note" :label="$t('common.notes')" outlined dense />
                  </div>
                  <div class="col-12 text-right">
                    <q-btn :label="$t('payments.addPayment')" type="submit" color="primary" :loading="saving" size="sm" />
                  </div>
                </div>
              </q-form>
            </q-card-section>
          </q-card>

          <!-- Payments List -->
           <q-list separator bordered>
             <q-item v-for="payment in paymentsValues" :key="payment.id">
               <q-item-section>
                 <q-item-label>{{ getMethodLabel(payment.payment_method) }} - {{ parseFloat(payment.amount).toFixed(2) }} DH</q-item-label>
                 <q-item-label caption>
                    {{ new Date(payment.payment_date).toLocaleDateString() }}
                    <span v-if="payment.reference"> | Ref: {{ payment.reference }}</span>
                 </q-item-label>
               </q-item-section>
               <q-item-section side>
                 <q-btn icon="delete" flat round color="negative" size="sm" @click="deletePayment(payment.id)" />
               </q-item-section>
             </q-item>
             <q-item v-if="paymentsValues.length === 0">
                <q-item-section class="text-center text-grey">{{ $t('payments.noPaymentsRecorded') }}</q-item-section>
             </q-item>
           </q-list>

        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="$t('common.close')" color="primary" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import { useQuasar } from 'quasar';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../api';
import CategorySelect from '../components/CategorySelect.vue';
import TaxSelect from '../components/TaxSelect.vue';


const $q = useQuasar();
const { t } = useI18n();
const invoices = ref([]);
const products = ref([]);
const suppliers = ref([]);
const units = ref([]);
const categories = ref([]);
const loading = ref(false);
const saving = ref(false);
const pdfLoading = ref(false);
const showDialog = ref(false);
const showProductDialog = ref(false);
const showSupplierDialog = ref(false);
const showPriceDialog = ref(false);
const showAttachDialog = ref(false);
const productOptions = ref([]);
const supplierOptions = ref([]);
const allProducts = ref([]);
const allSuppliers = ref([]);
const priceUpdates = ref([]);
const selectedInvoiceForAttach = ref(null);
const selectedInvoiceForPayment = ref(null);
const showPaymentDialog = ref(false);
const paymentsValues = ref([]);
const paymentForm = ref({
    amount: 0,
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'cash',
    reference: '',
    check_date: '',
    note: ''
});
const paymentMethods = [
    { label: t('payments.cash'), value: 'cash' },
    { label: t('payments.check'), value: 'check' },
    { label: t('payments.bankTransfer'), value: 'bank_transfer' },
];
const attachedFile = ref(null);
const settings = ref({
  semi_wholesale_percentage: 0,
  retail_percentage: 0,
});

const companySettings = ref({
  company_name: '',
  company_logo: '',
  phone: '',
  email: '',
  address: '',
});

const form = ref({
  supplier_id: null,
  invoice_number: '',
  invoice_date: new Date().toISOString().split('T')[0], // Default to today
  invoice_image: null,
  notes: '',
  manual_total: 0,
  items: [{ product_id: null, quantity: 1, purchase_price: 0 }],
});

const productForm = ref({
  name: '',
  product_description: '',
  unit_of_measure: '',
  current_stock_quantity: 0,
  product_code: '',
  barcode: '',
  cmup_cost: 0,
  tax_rate: 0,
  category_id: null,
  wholesale_price: 0,
  semi_wholesale_price: 0,
  retail_price: 0,
});

const supplierForm = ref({
  name: '',
  contact_person: '',
  phone: '',
  email: '',
  address: '',
  tax_id: '',
  notes: '',
});

const searchText = ref('');
const pagination = ref({
  rowsPerPage: 10
});
const showDetailsDialog = ref(false);
const selectedInvoice = ref(null);

const columns = computed(() => [
  { name: 'invoice_number', label: t('purchases.invoiceNumber'), field: 'invoice_number', align: 'left', sortable: true },
  { name: 'supplier', label: t('purchases.supplier'), align: 'left', sortable: true },
  { name: 'date', label: t('common.date'), field: 'invoice_date', align: 'left', sortable: true },
  { name: 'total_calculated', label: t('purchases.calculatedTotal'), align: 'right', sortable: true },
  { name: 'total', label: t('purchases.declaredTotal'), align: 'right', sortable: true },
  { name: 'payment_status', label: t('common.status'), align: 'center', sortable: true },
  { name: 'items_count', label: t('sales.itemsCount'), align: 'center', sortable: true },
  { name: 'image', label: t('sales.image'), align: 'center' },
  { name: 'actions', label: t('common.actions'), align: 'center' },
]);

const detailsColumns = computed(() => [
  { name: 'product', label: t('products.name'), align: 'left' },
  { name: 'unit', label: t('products.unit'), align: 'left' },
  { name: 'quantity', label: t('sales.quantity'), field: 'quantity', align: 'center' },
  { name: 'purchase_price', label: t('purchases.purchasePrice'), field: 'purchase_price', align: 'right', format: val => `${val.toFixed(2)} DH` },
  { name: 'subtotal', label: t('common.total'), align: 'right' },
]);

const calculatedTotal = computed(() => {
  return form.value.items.reduce((sum, item) => {
    return sum + (item.quantity * item.purchase_price);
  }, 0);
});

const totalDifference = computed(() => {
  if (!form.value.manual_total) return 0;
  return form.value.manual_total - calculatedTotal.value;
});

const totalDifferenceClass = computed(() => {
  if (totalDifference.value === 0 && form.value.manual_total > 0) {
    return 'text-positive';
  } else if (totalDifference.value !== 0) {
    return 'text-warning';
  }
  return '';
});

const filterMismatchOnly = ref(false);

const filteredInvoices = computed(() => {
  let result = invoices.value;
  
  // Apply mismatch filter
  if (filterMismatchOnly.value) {
    result = result.filter(invoice => invoice.it_has_def === true);
  }
  
  // Apply search filter
  if (searchText.value) {
    const needle = searchText.value.toLowerCase();
    result = result.filter(invoice => {
      return (
        invoice.invoice_number?.toLowerCase().includes(needle) ||
        invoice.supplier?.name?.toLowerCase().includes(needle) ||
        invoice.invoice_date?.toLowerCase().includes(needle)
      );
    });
  }
  
  return result;
});

const loadInvoices = async () => {
  loading.value = true;
  try {
    const response = await api.get('/purchases');
    invoices.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  } finally {
    loading.value = false;
  }
};

const toggleFilterMismatch = () => {
  filterMismatchOnly.value = !filterMismatchOnly.value;
};

const loadProducts = async () => {
  try {
    const response = await api.get('/products');
    products.value = response.data;
    allProducts.value = response.data;
    productOptions.value = response.data.map(p => ({ label: p.name, value: p.id }));
  } catch (error) {
    console.error('Failed to load products');
  }
};

const loadSuppliers = async () => {
  try {
    const response = await api.get('/suppliers');
    suppliers.value = response.data;
    allSuppliers.value = response.data;
    supplierOptions.value = response.data.map(s => ({ label: s.name, value: s.id }));
  } catch (error) {
    console.error('Failed to load suppliers');
  }
};

const loadCategories = async () => {
  try {
    const response = await api.get('/categories');
    categories.value = response.data;
  } catch (error) {
    console.error('Failed to load categories');
  }
};

const filterProducts = (val, update) => {
  if (val === '') {
    update(() => {
      productOptions.value = allProducts.value.map(p => ({ label: p.name, value: p.id }));
    });
    return;
  }

  update(() => {
    const needle = val.toLowerCase();
    productOptions.value = allProducts.value
      .filter(v => v.name.toLowerCase().indexOf(needle) > -1)
      .map(p => ({ label: p.name, value: p.id }));
  });
};

const filterSuppliers = (val, update) => {
  if (val === '') {
    update(() => {
      supplierOptions.value = allSuppliers.value.map(s => ({ label: s.name, value: s.id }));
    });
    return;
  }

  update(() => {
    const needle = val.toLowerCase();
    supplierOptions.value = allSuppliers.value
      .filter(v => v.name.toLowerCase().indexOf(needle) > -1)
      .map(s => ({ label: s.name, value: s.id }));
  });
};

const openProductDialog = () => {
  productForm.value = {
    name: '',
    product_description: '',
    unit_of_measure: '',
    current_stock_quantity: 0,
    product_code: '',
    barcode: '',
    cmup_cost: 0,
    tax_rate: 0,
    category_id: null,
    wholesale_price: 0,
    semi_wholesale_price: 0,
    retail_price: 0,
  };
  showProductDialog.value = true;
};

const openSupplierDialog = () => {
  supplierForm.value = {
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    tax_id: '',
    notes: '',
  };
  showSupplierDialog.value = true;
};

const saveQuickProduct = async () => {
  saving.value = true;
  try {
    const response = await api.post('/products', productForm.value);
    const newProduct = response.data;
    
    // Refresh products list
    await loadProducts();
    
    // Auto-select the new product in the last item row
    const lastItemIndex = form.value.items.length - 1;
    if (lastItemIndex >= 0) {
      form.value.items[lastItemIndex].product_id = newProduct.id;
    }
    
    $q.notify({ type: 'positive', message: t('messages.createdSuccessfully') });
    showProductDialog.value = false;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToSave') });
  } finally {
    saving.value = false;
  }
};

const saveQuickSupplier = async () => {
  saving.value = true;
  try {
    const response = await api.post('/suppliers', supplierForm.value);
    const newSupplier = response.data;
    
    // Refresh suppliers list
    await loadSuppliers();
    
    // Auto-select the new supplier
    form.value.supplier_id = newSupplier.id;
    
    $q.notify({ type: 'positive', message: t('messages.createdSuccessfully') });
    showSupplierDialog.value = false;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToSave') });
  } finally {
    saving.value = false;
  }
};

const handleFileUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    form.value.invoice_image = file;
  }
};

const addItem = () => {
  form.value.items.push({ product_id: null, quantity: 1, purchase_price: 0 });
};

const removeItem = (index) => {
  form.value.items.splice(index, 1);
};

const savePurchase = async () => {
  saving.value = true;
  try {
    const formData = new FormData();
    formData.append('supplier_id', form.value.supplier_id);
    
    // Get supplier name from suppliers list
    const supplier = suppliers.value.find(s => s.id === form.value.supplier_id);
    formData.append('supplier_name', supplier ? supplier.name : '');
    
    formData.append('invoice_number', form.value.invoice_number);
    formData.append('invoice_date', form.value.invoice_date);
    formData.append('total_amount', form.value.manual_total || 0); // Send manual total as total_amount
    formData.append('notes', form.value.notes || '');
    if (form.value.invoice_image) {
      formData.append('invoice_image', form.value.invoice_image);
    }
    formData.append('items', JSON.stringify(form.value.items));

    let response;
    if (form.value.id) {
      // Update existing invoice
      formData.append('_method', 'PUT');
      response = await api.post(`/purchases/${form.value.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      $q.notify({ type: 'positive', message: t('messages.updatedSuccessfully') });
    } else {
      // Create new invoice
      response = await api.post('/purchases', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      $q.notify({ type: 'positive', message: t('messages.createdSuccessfully') });
    }
    
    if (response.data.price_updates_required?.length > 0) {
      priceUpdates.value = response.data.price_updates_required;
      showPriceDialog.value = true;
    }
    
    closeDialog();
    loadInvoices();
  } catch (error) {
    $q.notify({ type: 'negative', message: error.response?.data?.message || t('messages.failedToSave') });
  } finally {
    saving.value = false;
  }
};

const allowMismatch = ref(false);

const handleSaveAttempt = () => {
  // If totals match, manual total is zero, or user enabled mismatch toggle, save directly
  if (totalDifference.value === 0 || form.value.manual_total === 0 || allowMismatch.value) {
    savePurchase();
  } else {
    // Show warning notification prompting user to enable mismatch toggle
    $q.notify({
      type: 'warning',
      message: t('purchases.totalMismatchWarning'),
    });
  }
};

const confirmSaveWithDifference = () => {
  $q.dialog({
    title: t('common.confirm'),
    message: t('purchases.confirmSaveWithDifference', { difference: Math.abs(totalDifference.value).toFixed(2) }),
    cancel: true,
    persistent: true,
    ok: {
      label: t('common.yes'),
      color: 'warning',
      icon: 'warning'
    },
    cancel: {
      label: t('common.cancel'),
      flat: true
    }
  }).onOk(() => {
    savePurchase();
  });
};

const updatePrices = async () => {
  saving.value = true;
  try {
    for (const update of priceUpdates.value) {
      await api.put(`/products/${update.product_id}/prices`, {
        wholesale_price: update.wholesale_price,
        semi_wholesale_price: update.semi_wholesale_price,
        retail_price: update.retail_price,
      });
    }
    $q.notify({ type: 'positive', message: t('messages.updatedSuccessfully') });
    showPriceDialog.value = false;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToSave') });
  } finally {
    saving.value = false;
  }
};

const closeDialog = () => {
  showDialog.value = false;
  form.value = {
    supplier_id: null,
    invoice_number: '',
    invoice_date: new Date().toISOString().split('T')[0], // Reset to today
    invoice_image: null,
    notes: '',
    manual_total: 0,
    items: [{ product_id: null, quantity: 1, purchase_price: 0 }],
  };
};

const viewImage = (path) => {
  const extension = path.split('.').pop().toLowerCase();
  const url = `http://localhost:8000/storage/${path}`;
  
  if (extension === 'pdf') {
    // For PDF, open in new tab with PDF viewer
    window.open(url, '_blank');
  } else {
    // For images, show in a dialog or open in new tab
    window.open(url, '_blank');
  }
};

const viewDetails = async (invoice) => {
  loading.value = true;
  try {
    // Fetch full invoice details with relationships
    const response = await api.get(`/purchases/${invoice.id}`);
    selectedInvoice.value = response.data;
    
    // Ensure company settings are loaded
    if (!companySettings.value.company_name) {
      await loadSettings();
    }
    
    showDetailsDialog.value = true;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  } finally {
    loading.value = false;
  }
};


const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return dateString;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const calculateSubtotal = () => {
  if (!selectedInvoice.value || !selectedInvoice.value.details) return 0;
  const subtotal = selectedInvoice.value.details.reduce((sum, item) => {
    const quantity = parseFloat(item.quantity) || 0;
    const price = parseFloat(item.purchase_price) || 0;
    return sum + (quantity * price);
  }, 0);
  return isNaN(subtotal) ? 0 : subtotal;
};

const getTotalAmount = () => {
  if (!selectedInvoice.value) return 0;
  
  // Try to get total_in_invoice first, then total_amount, then calculate
  const totalInInvoice = selectedInvoice.value.total_in_invoice;
  const totalAmount = selectedInvoice.value.total_amount;
  
  if (totalInInvoice !== null && totalInInvoice !== undefined) {
    return parseFloat(totalInInvoice) || 0;
  }
  
  if (totalAmount !== null && totalAmount !== undefined) {
    return parseFloat(totalAmount) || 0;
  }
  
  return calculateSubtotal();
};

const getLogoUrl = (logoPath) => {
  if (!logoPath) return '';
  return `http://localhost:8000/storage/${logoPath}`;
};

const saveAsPDF = async () => {
  if (!selectedInvoice.value) return;
  
  pdfLoading.value = true;
  try {
    const invoiceContent = document.getElementById('invoice-content');
    if (!invoiceContent) {
      $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
      return;
    }

    // Show loading notification
    $q.notify({
      type: 'info',
      message: t('messages.generatingPdf'),
      timeout: 2000
    });

    // A4 dimensions in mm
    const pageWidth = 210;
    const pageHeight = 297;
    const marginTop = 20;
    const marginBottom = 20;
    const marginLeft = 15;
    const marginRight = 15;
    const footerHeight = 10;
    
    const contentWidth = pageWidth - marginLeft - marginRight;
    const contentHeight = pageHeight - marginTop - marginBottom - footerHeight;
    
    // Create PDF
    const pdf = new jsPDF('p', 'mm', 'a4');
    
    // Get sections
    const headerSection = invoiceContent.querySelector('.invoice-header-section');
    const infoSection = invoiceContent.querySelector('.invoice-info-section');
    const transactionBar = invoiceContent.querySelector('.transaction-bar');
    const itemsTable = invoiceContent.querySelector('.invoice-table');
    const totalsSection = invoiceContent.querySelector('.invoice-totals-section');
    const notesSection = invoiceContent.querySelector('.invoice-notes-section');
    
    let currentY = marginTop;
    let currentPage = 1;
    
    // Function to add page number
    const addPageNumber = (page, total) => {
      // Clear any existing text in the area first (draw white rectangle)
      pdf.setFillColor(255, 255, 255);
      pdf.rect(pageWidth - marginRight - 35, pageHeight - 16, 35, 12, 'F');
      
      // Add page number with proper spacing
      pdf.setFontSize(11);
      pdf.setTextColor(40, 40, 40);
      pdf.setFont('helvetica', 'bold');
      const pageText = `${page} / ${total}`; // Add space around slash
      const textWidth = pdf.getTextWidth(pageText);
      pdf.text(pageText, pageWidth - marginRight - textWidth, pageHeight - 10);
    };
    
    // Function to check if we need a new page
    const checkNewPage = (requiredHeight) => {
      if (currentY + requiredHeight > pageHeight - marginBottom - footerHeight) {
        // Don't add page number here - will be added at the end
        pdf.addPage();
        currentPage++;
        currentY = marginTop;
        return true;
      }
      return false;
    };
    
    // Capture and add header section
    if (headerSection) {
      const headerCanvas = await html2canvas(headerSection, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const headerImg = headerCanvas.toDataURL('image/png');
      const headerHeight = (headerCanvas.height * contentWidth) / headerCanvas.width;
      
      checkNewPage(headerHeight);
      pdf.addImage(headerImg, 'PNG', marginLeft, currentY, contentWidth, headerHeight);
      currentY += headerHeight + 5;
    }
    
    // Capture and add info section
    if (infoSection) {
      const infoCanvas = await html2canvas(infoSection, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const infoImg = infoCanvas.toDataURL('image/png');
      const infoHeight = (infoCanvas.height * contentWidth) / infoCanvas.width;
      
      checkNewPage(infoHeight);
      pdf.addImage(infoImg, 'PNG', marginLeft, currentY, contentWidth, infoHeight);
      currentY += infoHeight + 5;
    }
    
    // Capture and add transaction bar
    if (transactionBar) {
      const barCanvas = await html2canvas(transactionBar, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const barImg = barCanvas.toDataURL('image/png');
      const barHeight = (barCanvas.height * contentWidth) / barCanvas.width;
      
      checkNewPage(barHeight);
      pdf.addImage(barImg, 'PNG', marginLeft, currentY, contentWidth, barHeight);
      currentY += barHeight + 5;
    }
    
    // Calculate totals section height
    let totalsHeight = 0;
    if (totalsSection) {
      const totalsCanvas = await html2canvas(totalsSection, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      totalsHeight = (totalsCanvas.height * contentWidth) / totalsCanvas.width;
    }
    
    // Process table with html2canvas (original method)
    if (itemsTable) {
      const tableRows = Array.from(itemsTable.querySelectorAll('tbody tr'));
      const tableHeader = itemsTable.querySelector('thead');
      
      if (tableRows.length === 0) {
        // No rows to process
        return;
      }
      
      // Store original display styles
      const originalRowStyles = tableRows.map(row => row.style.display);
      if (tableHeader) {
        tableHeader.style.display = '';
      }
      
      // Calculate max Y position for table (reserve space for totals)
      const maxYForTable = pageHeight - marginBottom - footerHeight - totalsHeight - 10;
      const minRowsOnLastPage = 2;
      
      let rowIndex = 0;
      const totalRows = tableRows.length;
      
      // Helper function to capture table portion
      const captureTablePortion = async (startRow, endRow, includeHeader = true) => {
        // Hide all rows first
        tableRows.forEach(row => row.style.display = 'none');
        
        // Show header if needed
        if (includeHeader && tableHeader) {
          tableHeader.style.display = '';
        } else if (tableHeader) {
          tableHeader.style.display = 'none';
        }
        
        // Show rows in range
        for (let i = startRow; i < endRow && i < totalRows; i++) {
          tableRows[i].style.display = '';
        }
        
        // Force reflow to ensure rendering (reduced delay for speed)
        await new Promise(resolve => {
          itemsTable.offsetHeight;
          void itemsTable.offsetWidth;
          setTimeout(resolve, 50); // Reduced from 150ms to 50ms
        });
        
        // Get the items section container
        const itemsSection = itemsTable.closest('.invoice-items-section');
        const containerToCapture = itemsSection || itemsTable;
        
        // Capture the visible portion (reduced scale for speed)
        try {
          const canvas = await html2canvas(containerToCapture, {
            scale: 1.5, // Reduced from 2 to 1.5 for faster rendering
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            allowTaint: false,
          });
          return canvas;
        } catch (error) {
          console.error('Error capturing table portion:', error);
          // Fallback: try capturing just the table
          const canvas = await html2canvas(itemsTable, {
            scale: 1.5, // Reduced from 2 to 1.5
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
          scale: 1.5, // Reduced from 2 to 1.5 for faster rendering
          useCORS: true,
          backgroundColor: '#ffffff',
        });
        headerImg = headerCanvas.toDataURL('image/png');
        headerHeight = (headerCanvas.height * contentWidth) / headerCanvas.width;
      }
      
      // Calculate actual row height by capturing first page rows (15 rows)
      let actualRowHeight = 0;
      const referenceRows = Math.min(15, totalRows); // Use first 15 rows as reference
      
      if (referenceRows > 0) {
        // Capture first page portion to calculate actual row height
        const firstPageCanvas = await captureTablePortion(0, referenceRows, true);
        const firstPageHeight = (firstPageCanvas.height * contentWidth) / firstPageCanvas.width;
        // Calculate average row height: (total height - header) / number of rows
        const rowsOnlyHeight = firstPageHeight - headerHeight;
        actualRowHeight = rowsOnlyHeight / referenceRows;
        
        // If calculated height is too small or too large, use fallback
        if (actualRowHeight < 5 || actualRowHeight > 15) {
          actualRowHeight = headerHeight > 0 ? headerHeight * 0.7 : 8;
        }
      } else {
        // Fallback if no rows
        actualRowHeight = headerHeight > 0 ? headerHeight * 0.7 : 8;
      }
      
      // Now process rows page by page using actual calculated height
      while (rowIndex < totalRows) {
        const remainingRows = totalRows - rowIndex;
        const needsNewPage = rowIndex > 0 && (currentY + headerHeight > maxYForTable);
        
        if (needsNewPage) {
          // Don't add page number here - will be added at the end
          pdf.addPage();
          currentPage++;
          currentY = marginTop;
          
          // Add table header on new page
          if (headerImg && headerHeight > 0) {
            pdf.addImage(headerImg, 'PNG', marginLeft, currentY, contentWidth, headerHeight);
            currentY += headerHeight;
          }
        }
        
        // Determine if totals should be on this page (before calculating rows)
        let spaceForTotals = 0;
        if (rowIndex === 0 && totalRows <= 10) {
          // First page with ≤10 rows: totals go on first page
          // Reserve minimal space to ensure all rows fit
          spaceForTotals = totalsHeight + 5;
        } else if (rowIndex === 0 && totalRows >= 11 && totalRows <= 16) {
          // First page with 11-16 rows: totals go on second page, no space reserved here
          spaceForTotals = 0;
        } else if (remainingRows <= 3) {
          // Last page: reserve space for totals
          spaceForTotals = totalsHeight + 10;
        }
        
        const maxYForRows = pageHeight - marginBottom - footerHeight - spaceForTotals;
        const availableSpace = maxYForRows - currentY;
        
        // Estimate how many rows fit (using actual calculated row height)
        let estimatedRowsToFit = Math.floor(availableSpace / actualRowHeight);
        estimatedRowsToFit = Math.max(1, Math.min(estimatedRowsToFit, remainingRows));
        
        // Check if this will complete the table
        const willCompleteTable = (rowIndex + estimatedRowsToFit) >= totalRows;
        
        // Smart logic for first page based on total rows:
        // - ≤ 10 rows: all rows + totals on first page
        // - 11-16 rows: (totalRows - 1) rows on first page, rest + totals on second page
        // - > 16 rows: 15 rows on first page, then normal pagination
        if (rowIndex === 0) {
          // First page logic
          if (totalRows <= 10) {
            // All rows + totals on first page
            estimatedRowsToFit = totalRows;
          } else if (totalRows >= 11 && totalRows <= 16) {
            // (totalRows - 1) rows on first page, rest + totals on second page
            estimatedRowsToFit = totalRows - 1;
          } else {
            // More than 16 rows: 15 rows on first page
            estimatedRowsToFit = 15;
          }
        } else {
          // Subsequent pages: target 23 rows (but consider last page needs)
          if (willCompleteTable && remainingRows > minRowsOnLastPage) {
            // If next page will be last, leave 2 rows for it
            const maxRowsForCurrentPage = remainingRows - minRowsOnLastPage;
            estimatedRowsToFit = Math.min(estimatedRowsToFit, maxRowsForCurrentPage, 23);
            estimatedRowsToFit = Math.max(1, estimatedRowsToFit);
          } else {
            // Normal page: use up to 23 rows
            estimatedRowsToFit = Math.min(estimatedRowsToFit, 23, remainingRows);
          }
        }
        
        // Now capture only this portion (one capture per page)
        let rowsGroupCanvas = await captureTablePortion(
          rowIndex, 
          rowIndex + estimatedRowsToFit, 
          rowIndex === 0
        );
        
        let rowsHeight = (rowsGroupCanvas.height * contentWidth) / rowsGroupCanvas.width;
        const isLastPortion = (rowIndex + estimatedRowsToFit) >= totalRows;
        
        // Adjust if actual height doesn't fit (only one retry)
        // BUT: If first page with ≤10 rows, we MUST fit all rows (don't reduce)
        const isFirstPageWithAllRows = (rowIndex === 0 && totalRows <= 10);
        if (rowsHeight > availableSpace && estimatedRowsToFit > 1 && !isFirstPageWithAllRows) {
          // Try with one less row
          estimatedRowsToFit--;
          rowsGroupCanvas = await captureTablePortion(
            rowIndex, 
            rowIndex + estimatedRowsToFit, 
            rowIndex === 0
          );
          rowsHeight = (rowsGroupCanvas.height * contentWidth) / rowsGroupCanvas.width;
        }
        
        let finalHeight = rowsHeight;
        
        // Add rows first at normal position
        pdf.addImage(rowsGroupCanvas.toDataURL('image/png'), 'PNG', marginLeft, currentY, contentWidth, finalHeight);
        currentY += finalHeight;
        
        // Add spacing after rows on last page (before totals)
        if (isLastPortion) {
          const targetTotalsY = pageHeight - marginBottom - footerHeight - totalsHeight;
          const availableSpaceForTotals = targetTotalsY - currentY;
          if (availableSpaceForTotals > 10) {
            const spacing = availableSpaceForTotals - 5; // Leave 5mm gap
            currentY += spacing; // Add spacing after rows
          }
        }
        
        rowIndex += estimatedRowsToFit;
      }
      
      // Restore original display styles
      tableRows.forEach((row, index) => {
        row.style.display = originalRowStyles[index] || '';
      });
      if (tableHeader) {
        tableHeader.style.display = '';
      }
    }
    
    // Add totals section at bottom of last page
    if (totalsSection) {
      // Calculate target position for totals (at bottom of page)
      const targetTotalsY = pageHeight - marginBottom - footerHeight - totalsHeight;
      
      // Check if totals fit on current page
      const totalsFitOnCurrentPage = currentY + totalsHeight <= pageHeight - marginBottom - footerHeight;
      
      if (!totalsFitOnCurrentPage) {
        // Totals don't fit, need new page
        // Don't add page number here - will be added at the end
        pdf.addPage();
        currentPage++;
        currentY = marginTop;
      }
      
      // Capture totals section
      const totalsCanvas = await html2canvas(totalsSection, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const totalsImg = totalsCanvas.toDataURL('image/png');
      
      // Calculate gap - the table extension should have filled most of it
      // Position totals at bottom (gap will appear as white space, which is fine)
      pdf.addImage(totalsImg, 'PNG', marginLeft, targetTotalsY, contentWidth, totalsHeight);
      currentY = targetTotalsY + totalsHeight;
    }
    
    // Add notes section if exists
    if (notesSection) {
      const notesCanvas = await html2canvas(notesSection, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const notesImg = notesCanvas.toDataURL('image/png');
      const notesHeight = (notesCanvas.height * contentWidth) / notesCanvas.width;
      
      checkNewPage(notesHeight);
      pdf.addImage(notesImg, 'PNG', marginLeft, currentY, contentWidth, notesHeight);
    }
    
    // Add page numbers to all pages
    const totalPages = pdf.internal.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
      pdf.setPage(i);
      addPageNumber(i, totalPages);
    }
    
    // Generate filename
    const invoiceNumber = selectedInvoice.value.invoice_number || 'invoice';
    const fileName = `invoice_${invoiceNumber}_${new Date().toISOString().split('T')[0]}.pdf`;
    
    // Save PDF
    pdf.save(fileName);
    
    $q.notify({ 
      type: 'positive', 
      message: t('messages.pdfGenerated'),
      timeout: 3000
    });
  } catch (error) {
    console.error('Error generating PDF:', error);
    $q.notify({ 
      type: 'negative', 
      message: t('messages.failedToGeneratePdf'),
      timeout: 3000
    });
  } finally {
    pdfLoading.value = false;
  }
};

const editInvoice = async (invoice) => {
  loading.value = true;
  try {
    // Fetch full invoice details with relationships
    const response = await api.get(`/purchases/${invoice.id}`);
    const invoiceData = response.data;
    
    // Populate form with invoice data
    form.value = {
      id: invoiceData.id,
      supplier_id: invoiceData.supplier_id,
      invoice_number: invoiceData.invoice_number,
      invoice_date: formatDate(invoiceData.invoice_date), // Convert ISO to yyyy-MM-dd
      invoice_image: null, // Don't pre-populate file input
      notes: invoiceData.notes || '',
      manual_total: invoiceData.total_in_invoice || invoiceData.total_amount,
      items: invoiceData.details.map(detail => ({
        product_id: detail.product_id,
        quantity: parseFloat(detail.quantity),
        purchase_price: parseFloat(detail.purchase_price)
      }))
    };
    
    // Open dialog
    showDialog.value = true;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('messages.failedToLoadData') });
  } finally {
    loading.value = false;
  }
};

const openAttachFileDialog = (invoice) => {
  selectedInvoiceForAttach.value = invoice;
  attachedFile.value = null;
  showAttachDialog.value = true;
};

const closeAttachDialog = () => {
  showAttachDialog.value = false;
  selectedInvoiceForAttach.value = null;
  attachedFile.value = null;
};

const onFileRejected = () => {
  $q.notify({
    type: 'negative',
    message: t('purchases.fileRejected')
  });
};

const uploadInvoiceFile = async () => {
  if (!attachedFile.value || !selectedInvoiceForAttach.value) return;
  
  saving.value = true;
  try {
    const formData = new FormData();
    formData.append('invoice_image', attachedFile.value);
    
    await api.post(`/purchases/${selectedInvoiceForAttach.value.id}/attach-file`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    
    $q.notify({ type: 'positive', message: t('purchases.docUploaded') });
    closeAttachDialog();
    loadInvoices();
  } catch (error) {
    $q.notify({ 
      type: 'negative', 
      message: error.response?.data?.message || t('purchases.uploadFailed') 
    });
  } finally {
    saving.value = false;
  }
};

const confirmDelete = (invoice) => {
  $q.dialog({
    title: t('purchases.confirmDeleteInvoiceTitle'),
    message: t('purchases.confirmDeleteInvoiceMessage', { number: invoice.invoice_number }),
    cancel: true,
    persistent: true,
    ok: {
      label: t('common.delete'),
      color: 'negative',
      icon: 'delete'
    },
    cancel: {
      label: t('common.cancel'),
      flat: true
    }
  }).onOk(() => {
    deleteInvoice(invoice.id);
  });
};

const deleteInvoice = async (id) => {
  loading.value = true;
  try {
    await api.delete(`/purchases/${id}`);
    $q.notify({ type: 'positive', message: t('purchases.invoiceDeletedSuccessfully') });
    loadInvoices();
  } catch (error) {
    $q.notify({ type: 'negative', message: error.response?.data?.message || t('purchases.invoiceDeleteFailed') });
  } finally {
    loading.value = false;
  }
};



const loadSettings = async () => {
  try {
    const response = await api.get('/settings');
    settings.value = response.data;
    // Also update company settings
    companySettings.value = {
      company_name: response.data.company_name || '',
      company_logo: response.data.company_logo || '',
      phone: response.data.phone || '',
      email: response.data.email || '',
      address: response.data.address || '',
    };
  } catch (error) {
    console.error('Failed to load settings');
  }
};

const calculateUpdatePrices = (val, update) => {
  const price = parseFloat(val) || 0;
  const semiPercent = parseFloat(settings.value.semi_wholesale_percentage) || 0;
  const retailPercent = parseFloat(settings.value.retail_percentage) || 0;

  update.semi_wholesale_price = parseFloat((price * (1 + semiPercent / 100)).toFixed(2));
  update.retail_price = parseFloat((price * (1 + retailPercent / 100)).toFixed(2));
};

const getFileIcon = (filePath) => {
  if (!filePath) return 'image';
  const extension = filePath.split('.').pop().toLowerCase();
  return extension === 'pdf' ? 'picture_as_pdf' : 'image';
};

const getFileColor = (filePath) => {
  if (!filePath) return 'primary';
  const extension = filePath.split('.').pop().toLowerCase();
  return extension === 'pdf' ? 'red' : 'primary';
};

const getFileTooltip = (filePath) => {
  if (!filePath) return t('purchases.viewDocument');
  const extension = filePath.split('.').pop().toLowerCase();
  return extension === 'pdf' ? t('purchases.viewInvoicePdf') : t('purchases.viewInvoiceImage');
};

const exportToExcel = () => {
  const data = filteredInvoices.value;
  
  if (data.length === 0) {
    $q.notify({ type: 'warning', message: t('common.noData') });
    return;
  }

  // Prepare CSV content
  const headers = [
    t('purchases.invoiceNumber'),
    t('purchases.supplier'),
    t('common.date'),
    t('common.total') + ' (DH)',
    t('payment.status'),
    t('sales.itemsCount'),
    t('common.notes')
  ];
  const csvRows = [];
  
  // Add UTF-8 BOM for Excel to recognize Arabic characters
  csvRows.push('\uFEFF');
  csvRows.push(headers.join(','));
  
  data.forEach(invoice => {
    // Calculate payment status
    const paymentInfo = getPaymentInfo(invoice);
    
    const row = [
      `"${invoice.invoice_number || ''}"`,
      `"${invoice.supplier?.name || 'N/A'}"`, 
      `"${invoice.invoice_date || ''}"`,
      parseFloat(invoice.total_amount || 0).toFixed(2),
      `"${paymentInfo.label}"`,
      invoice.details_count || 0,
      `"${invoice.notes || ''}"`
    ];
    csvRows.push(row.join(','));
  });
  
  // Create and download file
  const csvContent = csvRows.join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  
  link.setAttribute('href', url);
  link.setAttribute('download', `purchase_invoices_${new Date().toISOString().split('T')[0]}.csv`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

onMounted(() => {
  loadInvoices();
  loadProducts();
  loadSuppliers();
  loadSettings();
});
const openPaymentDialog = (invoice) => {
  selectedInvoiceForPayment.value = invoice;
  paymentForm.value = {
    amount: 0, 
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'cash',
    reference: '',
    check_date: '',
    note: ''
  };
  showPaymentDialog.value = true;
  fetchPayments(invoice.id);
};

const fetchPayments = async (invoiceId) => {
  try {
    const response = await api.get(`/purchases/${invoiceId}/payments`);
    paymentsValues.value = response.data;
  } catch (error) {
    console.error('Error fetching payments:', error);
    $q.notify({
      color: 'negative',
      message: t('messages.failedToLoadData')
    });
  }
};

const savePayment = async () => {
  if (paymentForm.value.amount <= 0) {
    $q.notify({ color: 'negative', message: t('messages.amountMustBePositive') });
    return;
  }
  
  saving.value = true;
  try {
    const payload = { ...paymentForm.value };
    if (!payload.check_date) payload.check_date = null;
    
    await api.post(`/purchases/${selectedInvoiceForPayment.value.id}/payments`, payload);
    $q.notify({ color: 'positive', message: t('payments.paymentAdded') });
    fetchPayments(selectedInvoiceForPayment.value.id);
    loadInvoices(); // Refresh main list to update status if needed
    
    // Reset form partially
    paymentForm.value.amount = 0;
    paymentForm.value.reference = '';
    paymentForm.value.note = '';
  } catch (error) {
    console.error('Error adding payment:', error);
    $q.notify({ color: 'negative', message: t('messages.error') });
  } finally {
    saving.value = false;
  }
};

const deletePayment = async (paymentId) => {
  $q.dialog({
    title: t('purchases.confirmDeleteTitle'),
    message: t('payments.confirmDeletePayment'),
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await api.delete(`/purchase-payments/${paymentId}`);
      $q.notify({ color: 'positive', message: t('payments.paymentDeleted') });
      fetchPayments(selectedInvoiceForPayment.value.id);
      loadInvoices();
    } catch (error) {
      console.error('Error deleting payment:', error);
      $q.notify({ color: 'negative', message: t('messages.error') });
    }
  });
};

const totalPaid = computed(() => {
  return paymentsValues.value.reduce((sum, p) => sum + parseFloat(p.amount), 0);
});

const remainingBalance = computed(() => {
  if (!selectedInvoiceForPayment.value) return 0;
  const total = parseFloat(selectedInvoiceForPayment.value.total_in_invoice || selectedInvoiceForPayment.value.total_amount || 0);
  return Math.max(0, total - totalPaid.value);
});

const getMethodLabel = (value) => {
  const method = paymentMethods.find(m => m.value === value);
  return method ? method.label : value;
};

const getPaymentInfo = (invoice) => {
  const total = parseFloat(invoice.total_in_invoice || invoice.total_amount || 0);
  const totalPaid = parseFloat(invoice.payments_sum_amount || 0);
  const pending = parseFloat(invoice.pending_amount || 0);
  const realPaid = totalPaid - pending;

  // Find pending checks to show in tooltip
  let tooltip = '';
  if (pending > 0 && invoice.payments) {
    const today = new Date();
    const pendingChecks = invoice.payments.filter(p => 
      p.payment_method === 'check' && 
      p.check_date && 
      new Date(p.check_date) > today
    );
    
    if (pendingChecks.length > 0) {
      const dates = pendingChecks.map(p => formatDate(p.check_date)).join(', ');
      tooltip = t('payments.checkDue') + `: ${dates}`;
    }
  }

  // Tolerance for float comparison
  const tolerance = 0.01;

  if (total <= 0) return { label: t('payments.paid'), color: 'positive', icon: 'check_circle', tooltip };

  if (realPaid >= total - tolerance) {
    return { label: t('payments.paid'), color: 'positive', icon: 'check_circle', tooltip };
  } else if (totalPaid >= total - tolerance) {
     return { label: t('payments.paidCheck'), color: 'info', icon: 'watch_later', tooltip };
  } else if (totalPaid > 0) {
    const remaining = total - totalPaid;
    const partialTooltip = tooltip ? `${tooltip} | ${t('payments.remaining')}: ${remaining.toFixed(2)} DH` : `${t('payments.remaining')}: ${remaining.toFixed(2)} DH`;
    return { label: t('payments.partial'), color: 'warning', icon: 'timelapse', tooltip: partialTooltip };
  } else {
    return { label: t('payments.unpaid'), color: 'negative', icon: 'cancel', tooltip };
  }
};
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}

.rounded-table :deep(thead tr th) {
  background-color: #1976d2;
  color: white;
  font-weight: bold;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.rounded-table :deep(thead tr:first-child th) {
  position: sticky;
  top: 0;
  z-index: 1;
}

.section-header {
  display: flex;
  align-items: center;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  color: #1976d2;
}

.section-header.compact {
  margin-top: 0.5rem;
  margin-bottom: 0.25rem;
}

.products-section {
  max-height: 300px;
  overflow-y: auto;
  padding-right: 4px;
}

.products-section::-webkit-scrollbar {
  width: 6px;
}

.products-section::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

.products-section::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 3px;
}

.products-section::-webkit-scrollbar-thumb:hover {
  background: #555;
}

.col-2-5 {
  width: 20.83%;
  flex: 0 0 20.83%;
  max-width: 20.83%;
}

/* Invoice Styles */
.invoice-card {
  background: white;
  max-width: 210mm; /* A4 width */
  width: 100%;
  margin: 0 auto;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

@media (max-width: 900px) {
  .invoice-card {
    max-width: 100%;
  }
  
  .invoice-content {
    max-width: 100%;
    padding: 15mm 10mm;
  }
}

.invoice-header {
  border-bottom: 1px solid #e0e0e0;
}

.invoice-content {
  background: linear-gradient(to bottom, #f0f9f4 0%, #ffffff 15%, #ffffff 85%, #e8f4fb 100%);
  min-height: calc(100vh - 120px);
  padding: 20mm 15mm; /* A4 padding */
  max-width: 210mm; /* A4 width */
  margin: 0 auto;
  box-sizing: border-box;
}

.invoice-header-section {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid #e0e0e0;
}

.invoice-label {
  font-size: 10px;
  font-weight: bold;
  color: #666;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.invoice-value {
  font-size: 14px;
  font-weight: 500;
  color: #333;
}

.company-name {
  font-size: 18px;
  color: #1976d2;
}

.logo-placeholder {
  text-align: center;
}

.invoice-info-section {
  margin: 30px 0;
  padding: 20px 0;
}

.invoice-info {
  font-size: 13px;
  color: #555;
  line-height: 1.8;
}

.transaction-bar {
  background: linear-gradient(135deg, #c8e6c9 0%, #b2dfdb 100%);
  padding: 15px 20px;
  margin: 20px 0;
  border-radius: 4px;
}

.transaction-label {
  font-size: 10px;
  font-weight: bold;
  color: #333;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 5px;
}

.transaction-value {
  font-size: 13px;
  color: #333;
  font-weight: 500;
}

.invoice-items-section {
  margin: 30px 0;
}

.invoice-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.invoice-table thead {
  background: linear-gradient(135deg, #c8e6c9 0%, #b2dfdb 100%);
}

.invoice-table th {
  padding: 12px 15px;
  text-align: left;
  vertical-align: middle;
  font-weight: bold;
  font-size: 11px;
  color: #333;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #81c784;
}

.invoice-table th.text-right {
  text-align: right;
}

.invoice-table td {
  padding: 5px 15px;
  border-bottom: 1px solid #e0e0e0;
  vertical-align: middle;
  font-size: 13px;
  color: #555;
}

.invoice-table tbody tr:nth-child(odd) {
  background-color: #ffffff;
}

.invoice-table tbody tr:nth-child(even) {
  background-color: #f0f9f4;
}

.invoice-table tbody tr:hover {
  background-color: #e8f5e9;
}

.invoice-table tbody tr:last-child td {
  border-bottom: none;
}

.invoice-totals-section {
  margin: 30px 0;
  padding-top: 20px;
}

.totals-box {
  background: linear-gradient(135deg, #e3f2fd 0%, #b3e5fc 100%);
  padding: 20px 30px;
  border-radius: 8px;
  min-width: 300px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid rgba(255,255,255,0.5);
}

.total-row:last-child {
  border-bottom: none;
  margin-top: 10px;
  padding-top: 15px;
  border-top: 2px solid rgba(255,255,255,0.8);
}

.total-label {
  font-size: 14px;
  font-weight: 500;
  color: #333;
  text-transform: uppercase;
}

.total-value {
  font-size: 16px;
  font-weight: 600;
  color: #333;
}

.total-value.final {
  font-size: 20px;
  font-weight: bold;
  color: #1976d2;
}

.invoice-notes-section {
  margin-top: 30px;
  padding: 15px;
  background: #f5f5f5;
  border-radius: 4px;
  border-left: 4px solid #1976d2;
}

.invoice-notes {
  font-size: 13px;
  color: #555;
  line-height: 1.6;
  white-space: pre-wrap;
}

.invoice-actions {
  border-top: 1px solid #e0e0e0;
  padding: 15px;
}

@media print {
  .invoice-header,
  .invoice-actions {
    display: none !important;
  }
  
  .invoice-card {
    max-width: 210mm !important;
    width: 210mm !important;
    margin: 0 !important;
    box-shadow: none !important;
  }
  
  .invoice-content {
    background: white !important;
    padding: 20mm 15mm !important;
    max-width: 210mm !important;
    width: 210mm !important;
    min-height: 297mm !important;
    margin: 0 !important;
    box-sizing: border-box !important;
  }
  
  @page {
    size: A4;
    margin: 0;
  }
  
  body {
    margin: 0;
    padding: 0;
  }
}
</style>
