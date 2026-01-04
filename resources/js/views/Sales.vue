<template>
  <q-page class="q-pa-md">
    <div class="row items-center justify-between q-mb-md">
      <div class="text-h4">{{ $t('sales.title') }}</div>
      <q-btn color="primary" icon="add" :label="$t('sales.newSale')" @click="openDialog" />
    </div>

    <q-table
      :rows="receipts"
      :columns="columns"
      row-key="id"
      :loading="loading"
      flat
      bordered
      class="rounded-table"
      :rows-per-page-label="$t('common.rowsPerPage')"
      :no-data-label="$t('common.noData')"
      :loading-label="$t('common.loading')"
      :pagination="{ rowsPerPage: 20 }"
    >
      <template v-slot:body-cell-receipt_number="props">
        <q-td :props="props">
          <div class="text-weight-bold">{{ props.value }}</div>
        </q-td>
      </template>
      <template v-slot:body-cell-distributor="props">
        <q-td :props="props">
          {{ props.row.distributor?.name || 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-client="props">
        <q-td :props="props">
          {{ props.row.client?.name || 'N/A' }}
        </q-td>
      </template>
      <template v-slot:body-cell-items_count="props">
        <q-td :props="props" class="text-center">
          <q-badge color="primary">{{ props.row.details?.length || 0 }}</q-badge>
        </q-td>
      </template>
      <template v-slot:body-cell-date="props">
        <q-td :props="props">
          {{ formatDate(props.row.receipt_date) }}
        </q-td>
      </template>
      <template v-slot:body-cell-total="props">
        <q-td :props="props" class="text-right text-weight-bold">
          {{ calculateReceiptTotal(props.row).toFixed(2) }} DH
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

      <template v-slot:body-cell-image="props">
        <q-td :props="props">
          <q-btn 
            v-if="props.row.receipt_image_path" 
            flat 
            dense 
            :icon="getFileIcon(props.row.receipt_image_path)" 
            :color="getFileIconColor(props.row.receipt_image_path)" 
            @click="viewImage(props.row.receipt_image_path)"
          >
            <q-tooltip>{{ getFileTooltip(props.row.receipt_image_path) }}</q-tooltip>
          </q-btn>
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <div class="row items-center justify-center q-gutter-xs">
            <q-btn flat dense icon="visibility" color="positive" @click="viewReceipt(props.row)">
              <q-tooltip>{{ $t('common.view') }}</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="attach_money" color="positive" @click="openPaymentDialog(props.row)">
              <q-tooltip>{{ $t('payments.title') }}</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="edit" color="primary" @click="editReceipt(props.row)">
              <q-tooltip>{{ $t('common.edit') }}</q-tooltip>
            </q-btn>
            <q-btn flat dense icon="delete" color="negative" @click="confirmDelete(props.row)">
              <q-tooltip>{{ $t('common.delete') }}</q-tooltip>
            </q-btn>
          </div>
        </q-td>
      </template>
    </q-table>

    <!-- New/Edit Sale Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 900px; max-width: 95vw;">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ isEditing ? $t('sales.editSale') : $t('sales.newSale') }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit.prevent="saveSale">
            <!-- Row 1: Distributor, Receipt Number, Receipt Date -->
            <div class="row q-col-gutter-md q-mb-md">
              <div class="col-4">
                <q-select
                  v-model="form.distributor_id"
                  :options="distributorOptions"
                  option-value="id"
                  option-label="name"
                  :label="$t('sales.distributor') + ' *'"
                  outlined
                  dense
                  emit-value
                  map-options
                  :rules="[val => !!val || $t('messages.required')]"
                  @update:model-value="loadVanStocks"
                />
              </div>
              <div class="col-4">
                <q-input
                  v-model="form.receipt_number"
                  :label="$t('sales.receiptNumber')"
                  outlined
                  dense
                  readonly
                  :rules="[val => !!val || $t('messages.required')]"
                />
              </div>
              <div class="col-4">
                <q-input
                  v-model="form.receipt_date"
                  :label="$t('sales.receiptDate')"
                  type="date"
                  outlined
                  dense
                  :rules="[val => !!val || $t('messages.required')]"
                />
              </div>
            </div>

            <!-- Row 2: Customer Name and Receipt Image -->
            <div class="row q-col-gutter-md q-mb-md">
              <div class="col-6">
                <q-select
                  v-model="form.client_id"
                  :options="clientOptions"
                  option-value="id"
                  option-label="name"
                  :label="$t('sales.client')"
                  outlined
                  dense
                  emit-value
                  map-options
                  use-input
                  input-debounce="300"
                  :loading="searchingClients"
                  @filter="filterClients"
                  @update:model-value="handleClientSelection"
                  :rules="[val => !!val || $t('messages.required')]"
                >
                  <template v-slot:option="{ itemProps, opt }">
                    <q-item v-bind="itemProps">
                      <q-item-section>
                        <q-item-label>{{ opt.name }}</q-item-label>
                        <q-item-label caption v-if="opt.phone">{{ opt.phone }}</q-item-label>
                      </q-item-section>
                    </q-item>
                  </template>
                  <template v-slot:no-option="{ inputValue }">
                    <q-item>
                      <q-item-section class="text-grey">
                        {{ $t('common.noData') }} "{{ inputValue }}"
                      </q-item-section>
                    </q-item>
                    <q-item clickable @click="openAddClientDialog(inputValue)">
                      <q-item-section avatar>
                        <q-icon name="add" color="primary" />
                      </q-item-section>
                      <q-item-section>
                        <q-item-label class="text-primary">{{ $t('sales.addNewClient') }} "{{ inputValue }}"</q-item-label>
                      </q-item-section>
                    </q-item>
                  </template>
                  <template v-slot:after-options>
                    <q-item clickable @click="openAddClientDialog('')">
                      <q-item-section avatar>
                        <q-icon name="add" color="primary" />
                      </q-item-section>
                      <q-item-section>
                        <q-item-label class="text-primary">{{ $t('sales.addNewClient') }}</q-item-label>
                      </q-item-section>
                    </q-item>
                  </template>
                </q-select>
              </div>
              <div class="col-6">
                <q-file
                  v-model="form.receipt_image"
                  :label="$t('sales.receiptImage')"
                  outlined
                  dense
                  accept="image/*,.pdf"
                  clearable
                >
                  <template v-slot:prepend>
                    <q-icon name="attach_file" />
                  </template>
                </q-file>
              </div>
            </div>

            <!-- Items Section -->
            <div class="text-subtitle2 q-mt-md q-mb-sm">{{ $t('common.items') }}</div>
            
            <q-table
              :rows="form.items"
              :columns="itemColumns"
              row-key="id"
              flat
              bordered
              class="q-mb-md"
              separator="cell"
              hide-bottom
              :no-data-label="$t('common.noData')"
              :loading-label="$t('common.loading')"
            >
              <template v-slot:body-cell-product="props">
                <q-td :props="props">
                  <q-select
                      v-model="props.row.product_id"
                    :options="vanProductOptions"
                    option-value="id"
                    option-label="name"
                    outlined
                    dense
                    emit-value
                    map-options
                    use-input
                    input-debounce="300"
                    @filter="filterProducts"
                    @update:model-value="(val) => handleProductSelection(props.row, val)"
                  >
                    <template v-slot:option="{ itemProps, opt }">
                      <q-item v-bind="itemProps" v-if="shouldShowProductOption(opt, props.row.id)">
                        <q-item-section>
                          <q-item-label>{{ opt.name }}</q-item-label>
                        </q-item-section>
                      </q-item>
                    </template>
                  </q-select>
                </q-td>
              </template>
              
              <template v-slot:body-cell-quantity="props">
                <q-td :props="props">
                  <div class="row items-center no-wrap q-gutter-xs">
                    <!-- Quantity Input (wider) -->
                    <q-input
                      v-model.number="props.row.quantity"
                      type="number"
                      outlined
                      dense
                      :label="$t('sales.quantity')"
                      :min="0"
                      @update:model-value="validateQuantity(props.row)"
                      style="width: 70px;"
                    />
                    <!-- Promo Checkbox -->
                    <q-checkbox 
                      v-model="props.row.has_promo" 
                      dense 
                      size="xs" 
                      :label="$t('sales.promo')" 
                      class="q-ml-xs"
                    />
                    <!-- Promo Input (only if checked) -->
                    <q-input
                      v-if="props.row.has_promo"
                      v-model.number="props.row.promo_quantity"
                      type="number"
                      outlined
                      dense
                      :label="$t('sales.promo')"
                      bg-color="yellow-1"
                      :min="0"
                      @update:model-value="validateQuantity(props.row)"
                      style="width: 55px;"
                    />
                    <!-- Max Stock Display -->
                    <span class="text-grey-7 text-caption" v-if="props.row.product_id">
                      /{{ formatQuantity(getVanStockQuantity(props.row.product_id)) }}
                    </span>
                  </div>
                </q-td>
              </template>
              
              <template v-slot:body-cell-price_type="props">
                <q-td :props="props">
                  <q-select
                    v-model="props.row.price_type_used"
                    :options="getFilteredPriceTypes(props.row)"
                    option-value="value"
                    option-label="label"
                    outlined
                    dense
                    emit-value
                    map-options
                    @update:model-value="() => updatePriceForItem(props.row)"
                  />
                </q-td>
              </template>
              
              <template v-slot:body-cell-unit_price="props">
                <q-td :props="props">
                  <q-input
                    v-model.number="props.row.selling_price"
                    type="number"
                    outlined
                    dense
                    step="0.01"
                    :min="0"
                  />
                </q-td>
              </template>
              
              <template v-slot:body-cell-total_price="props">
                <q-td :props="props" class="text-right text-weight-medium">
                  {{ (props.row.quantity * props.row.selling_price).toFixed(2) }} DH
                </q-td>
              </template>
              
              <template v-slot:body-cell-actions="props">
                <q-td :props="props" class="text-center">
                  <q-btn flat dense icon="delete" color="negative" @click="removeItem(props.rowIndex)" />
                </q-td>
              </template>

              <!-- Add Item button row at the bottom -->
              <template v-slot:bottom-row>
                <q-tr v-if="hasMoreProducts">
                  <q-td colspan="6">
                    <q-btn 
                      flat 
                      dense 
                      icon="add" 
                      :label="$t('sales.addItem')" 
                      color="primary" 
                      class="full-width" 
                      @click="addItem" 
                    />
                  </q-td>
                </q-tr>
              </template>
            </q-table>

            <!-- Grand Total -->
            <div class="row justify-end q-mb-md">
              <div class="col-auto">
                <q-card flat bordered class="bg-grey-2">
                  <q-card-section class="q-pa-sm">
                    <div class="row items-center q-gutter-md">
                      <div class="text-subtitle1 text-weight-medium">{{ $t('sales.grandTotal') }}:</div>
                      <div class="text-h6 text-primary text-weight-bold">{{ grandTotal.toFixed(2) }} DH</div>
                    </div>
                  </q-card-section>
                </q-card>
              </div>
            </div>

            <div class="row justify-end q-gutter-sm">
              <q-btn :label="$t('common.cancel')" flat @click="closeDialog" />
              <q-btn type="submit" :label="$t('common.save')" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
    <!-- Add Client Dialog -->
    <q-dialog v-model="showAddClientDialog" persistent>
      <q-card style="min-width: 400px;">
        <q-card-section class="bg-secondary text-white">
          <div class="text-h6">{{ $t('sales.addNewClient') }}</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit.prevent="saveNewClient" class="q-gutter-md">
            <q-input
              v-model="newClientForm.name"
              :label="$t('clients.name') + ' *'"
              outlined
              dense
              :rules="[val => !!val || $t('messages.required')]"
            />

            <q-input
              v-model="newClientForm.phone"
              :label="$t('clients.phone')"
              outlined
              dense
            />

            <q-input
              v-model="newClientForm.address"
              :label="$t('clients.address')"
              outlined
              dense
            />

            <div class="row justify-end q-gutter-sm">
              <q-btn :label="$t('common.cancel')" flat @click="showAddClientDialog = false" />
              <q-btn type="submit" :label="$t('common.save')" color="secondary" :loading="savingClient" />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Sales Receipt Preview Dialog -->
    <q-dialog v-model="showReceiptDialog">
      <q-card style="width: 800px; max-width: 95vw;">
        <q-card-section class="row items-center q-pa-sm bg-primary text-white">
          <q-btn flat dense icon="close" @click="showReceiptDialog = false" />
          <div class="text-h6 q-ml-md">{{ $t('sales.salesReceipt') }}</div>
          <q-space />
          <q-btn 
            flat 
            dense 
            icon="picture_as_pdf" 
            :label="$t('sales.saveAsPdf')" 
            @click="saveReceiptAsPDF"
            :loading="pdfLoading"
          />
        </q-card-section>

        <q-card-section v-if="selectedReceipt" class="receipt-content" id="receipt-content">
          <!-- Receipt Header -->
          <div class="receipt-header-section">
            <div class="row items-start justify-between">
              <!-- Left: Date and Receipt Number -->
              <div class="col-3">
                <div class="receipt-label q-mb-xs">{{ $t('common.date').toUpperCase() }}</div>
                <div class="receipt-value">{{ formatDate(selectedReceipt.receipt_date) }}</div>
                <div class="receipt-label q-mt-md q-mb-xs">{{ $t('sales.receiptNumber').toUpperCase() }}</div>
                <div class="receipt-value">{{ selectedReceipt.receipt_number }}</div>
              </div>

              <!-- Center: Title -->
              <div class="col-5 text-center">
                <div class="text-h4 text-weight-bold q-mb-md">{{ $t('sales.salesReceipt').toUpperCase() }}</div>
                <div class="company-name text-weight-bold">{{ companySettings.company_name || 'YOUR COMPANY' }}</div>
              </div>

              <!-- Right: Logo -->
              <div class="col-3 text-right">
                <div v-if="companySettings.company_logo" class="company-logo q-mb-md">
                  <img :src="getLogoUrl(companySettings.company_logo)" alt="Company Logo" style="max-width: 100px; max-height: 100px;" />
                </div>
                <div v-else class="logo-placeholder q-mb-md">
                  <q-icon name="business" size="80px" color="grey-4" />
                  <div class="text-caption text-grey-6">{{ $t('sales.logoName') }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Distributor and Client Info -->
          <div class="receipt-info-section">
            <div class="row q-col-gutter-md">
              <!-- Distributor Info -->
              <div class="col-6">
                <div class="receipt-label q-mb-xs">{{ $t('sales.distributor').toUpperCase() }}</div>
                <div class="receipt-info">
                  <div class="text-weight-bold">{{ selectedReceipt.distributor?.name || 'N/A' }}</div>
                  <div v-if="selectedReceipt.distributor?.phone">Phone: {{ selectedReceipt.distributor.phone }}</div>
                </div>
              </div>

              <!-- Client Info -->
              <div class="col-6 text-right">
                <div class="receipt-label q-mb-xs">{{ $t('sales.client').toUpperCase() }}</div>
                <div class="receipt-info">
                  <div class="text-weight-bold">{{ selectedReceipt.client?.name || 'N/A' }}</div>
                  <div v-if="selectedReceipt.client?.phone">Phone: {{ selectedReceipt.client.phone }}</div>
                  <div v-if="selectedReceipt.client?.address">{{ selectedReceipt.client.address }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Items Table -->
          <div class="receipt-items-section" v-if="selectedReceipt.details && selectedReceipt.details.length > 0">
            <table class="receipt-table">
              <thead>
                <tr>
                  <th style="width: 5%">#</th>
                  <th style="width: 35%">{{ $t('products.description').toUpperCase() }}</th>
                  <th style="width: 15%">{{ $t('sales.priceType').toUpperCase() }}</th>
                  <th style="width: 10%">{{ $t('sales.quantity').toUpperCase() }}</th>
                  <th style="width: 15%" class="text-right">{{ $t('sales.unitPrice').toUpperCase() }}</th>
                  <th style="width: 20%" class="text-right">{{ $t('sales.lineTotal').toUpperCase() }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in selectedReceipt.details" :key="index">
                  <td class="text-center">{{ index + 1 }}</td>
                  <td>
                    <div class="text-weight-medium">{{ item.product?.name || 'Product' }}</div>
                  </td>
                  <td>{{ getPriceTypeLabel(item.price_type_used) }}</td>
                  <td>
                    {{ parseFloat(item.quantity).toFixed(2) }}
                    <div v-if="parseFloat(item.promo_quantity) > 0" class="text-positive text-caption text-weight-bold">
                        + {{ parseFloat(item.promo_quantity).toFixed(2) }} {{ $t('sales.free') }}
                    </div>
                  </td>
                  <td class="text-right">{{ parseFloat(item.selling_price).toFixed(2) }} DH</td>
                  <td class="text-right">{{ (parseFloat(item.quantity) * parseFloat(item.selling_price)).toFixed(2) }} DH</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-center q-pa-md text-grey-6">
            {{ $t('sales.noItems') }}
          </div>

          <!-- Totals Section -->
          <div class="receipt-totals-section">
            <div class="row justify-end">
              <div class="totals-box">
                <div class="total-row">
                  <span class="total-label">{{ $t('sales.subtotal') }}</span>
                  <span class="total-value">{{ calculateReceiptTotal(selectedReceipt).toFixed(2) }} DH</span>
                </div>
                <div class="total-row">
                  <span class="total-label">{{ $t('common.total') }}</span>
                  <span class="total-value final">{{ calculateReceiptTotal(selectedReceipt).toFixed(2) }} DH</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes Section -->
          <div class="receipt-notes-section" v-if="selectedReceipt.notes">
            <div class="receipt-label q-mb-xs">{{ $t('common.notes').toUpperCase() }}</div>
            <div class="receipt-notes">{{ selectedReceipt.notes }}</div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="receipt-actions">
          <q-btn :label="$t('common.close')" flat @click="showReceiptDialog = false" />
          <q-btn 
            :label="$t('sales.saveAsPdf')" 
            icon="picture_as_pdf" 
            color="primary"
            @click="saveReceiptAsPDF"
            :loading="pdfLoading"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Payment History Dialog -->
    <q-dialog v-model="showPaymentDialog">
      <q-card style="min-width: 600px">
        <q-card-section class="bg-primary text-white">
          <div class="text-h6">{{ $t('purchases.paymentHistory') }}</div>
          <div class="text-subtitle2" v-if="selectedReceiptForPayment">
            {{ $t('sales.receiptNumber') }} #{{ selectedReceiptForPayment.receipt_number }} - {{ selectedReceiptForPayment.client?.name }}
          </div>
        </q-card-section>

        <q-card-section class="q-pa-md">
          <div class="row q-col-gutter-md q-mb-lg">
             <div class="col-4">
                <div class="text-caption text-grey">{{ $t('payments.totalReceipt') }}</div>
                <div class="text-h6">{{ calculateReceiptTotal(selectedReceiptForPayment || {}).toFixed(2) }} DH</div>
             </div>
             <div class="col-4">
                <div class="text-caption text-grey">{{ $t('payments.totalPaid') }}</div>
                <div class="text-h6 text-positive">{{ totalPaid.toFixed(2) }} DH</div>
             </div>
              <div class="col-4">
                 <div class="text-caption text-grey">{{ $t('payments.remaining') }}</div>
                 <div class="text-h6 text-negative">
                    {{ remainingBalance.toFixed(2) }} DH
                 </div>
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
                  <div class="col-6">
                    <q-input 
                        v-model="paymentForm.reference" 
                        :label="paymentForm.payment_method === 'check' ? $t('payments.checkNo') : (paymentForm.payment_method === 'bank_transfer' ? $t('payments.refNo') : $t('payments.receiptNo'))" 
                        outlined 
                        dense 
                    />
                  </div>
                  <div class="col-6" v-if="paymentForm.payment_method === 'check'">
                    <q-input v-model="paymentForm.check_date" :label="$t('payments.checkDate')" type="date" outlined dense />
                  </div>
                  <div class="col-12">
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

const $q = useQuasar();
const { t } = useI18n();
const receipts = ref([]);
const loading = ref(false);
const saving = ref(false);
const showDialog = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const allDistributors = ref([]);
const vanProducts = ref([]);
const vanProductOptions = ref([]);
const allProducts = ref([]);

// Receipt preview
const showReceiptDialog = ref(false);
const selectedReceipt = ref(null);
const pdfLoading = ref(false);
const companySettings = ref({});

// Client-related refs
const clientOptions = ref([]);
const allClients = ref([]);
const searchingClients = ref(false);
const showAddClientDialog = ref(false);
const savingClient = ref(false);
const newClientForm = ref({
  name: '',
  phone: '',
  address: '',
});

const form = ref({
  receipt_number: '',
  distributor_id: null,
  client_id: null,
  receipt_date: new Date().toISOString().split('T')[0],
  receipt_image: null,
  items: [],
});

const columns = computed(() => [
  { name: 'receipt_number', label: t('sales.receiptNumber'), field: 'receipt_number', align: 'left', sortable: true },
  { name: 'distributor', label: t('sales.distributor'), field: 'distributor_id', align: 'left', sortable: true },
  { name: 'client', label: t('sales.client'), field: 'client_id', align: 'left', sortable: true },
  { name: 'date', label: t('common.date'), field: 'receipt_date', align: 'left', sortable: true },
  { name: 'items_count', label: t('sales.itemsCount'), field: row => row.details?.length || 0, align: 'center', sortable: true },
  { name: 'total', label: t('common.total'), field: row => calculateReceiptTotal(row), align: 'right', sortable: true },
  { name: 'payment_status', label: t('common.status'), align: 'center', sortable: true },
  { name: 'image', label: t('sales.image'), field: 'receipt_image_path', align: 'center' },
  { name: 'actions', label: t('common.actions'), field: 'actions', align: 'center' },
]);

const selectedReceiptForPayment = ref(null);
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

// Filter distributors for dropdown (only show those with stock for new sales)
const distributorOptions = computed(() => {
  if (isEditing.value) return allDistributors.value; // Show all when editing existing receipt
  return allDistributors.value.filter(d => d.stock_items_count > 0);
});

// Check if there are more products available to add
const hasMoreProducts = computed(() => {
  if (!vanProducts.value || vanProducts.value.length === 0) return false;
  
  // 1. Calculate how many products are "available" (not yet selected in a row with a value)
  const availableProductsCount = vanProducts.value.filter(p => {
     if (p.available_quantity <= 0) return false;
     // Check if selected in any row (only counts rows that strictly match the ID)
     const isSelected = form.value.items.some(item => item.product_id === p.id);
     return !isSelected;
  }).length;
  
  // 2. Count "Empty" rows that are waiting for a product
  // These rows "consume" the availability potential
  const emptyRowsCount = form.value.items.filter(item => !item.product_id).length;

  // 3. Only show "Add Item" if we have more available products than current empty rows
  return availableProductsCount > emptyRowsCount;
});

const itemColumns = computed(() => [
  { name: 'product', label: t('purchases.product'), field: 'product_id', align: 'left', style: 'width: 30%' },
  { name: 'quantity', label: t('sales.quantity'), field: 'quantity', align: 'left', style: 'width: 25%' },
  { name: 'price_type', label: t('sales.priceType'), field: 'price_type_used', align: 'left', style: 'width: 15%' },
  { name: 'unit_price', label: t('sales.unitPrice'), field: 'selling_price', align: 'right', style: 'width: 15%' },
  { name: 'total_price', label: t('common.total'), field: row => (row.quantity * row.selling_price).toFixed(2), align: 'right', style: 'width: 10%' },
  { name: 'actions', label: '', field: 'actions', align: 'center', style: 'width: 50px' },
]);

// Computed property for grand total
const grandTotal = computed(() => {
  return form.value.items.reduce((sum, item) => {
    return sum + (item.quantity * item.selling_price);
  }, 0);
});

const priceTypeOptions = computed(() => [
  { label: t('sales.wholesale'), value: 'wholesale' },
  { label: t('sales.semiWholesale'), value: 'semi_wholesale' },
  { label: t('sales.retail'), value: 'retail' },
]);

const loadReceipts = async () => {
  loading.value = true;
  try {
    const response = await api.get('/sales');
    receipts.value = response.data;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('sales.failedToLoadReceipts') });
  } finally {
    loading.value = false;
  }
};

const loadDistributors = async () => {
  try {
    const response = await api.get('/distributors');
    allDistributors.value = response.data;
  } catch (error) {
    console.error('Failed to load distributors:', error);
    $q.notify({ type: 'negative', message: t('sales.failedToLoadDistributors') });
  }
};

const loadClients = async () => {
  try {
    const response = await api.get('/clients');
    allClients.value = response.data;
    clientOptions.value = response.data;
  } catch (error) {
    console.error('Failed to load clients:', error);
  }
};

const filterClients = async (val, update, abort) => {
  if (val.length < 1) {
    update(() => {
      clientOptions.value = allClients.value;
    });
    return;
  }

  searchingClients.value = true;
  try {
    const response = await api.get('/clients/search', { params: { q: val } });
    update(() => {
      clientOptions.value = response.data;
    });
  } catch (error) {
    console.error('Failed to search clients:', error);
    update(() => {
      clientOptions.value = allClients.value.filter(
        (c) => c.name.toLowerCase().includes(val.toLowerCase())
      );
    });
  } finally {
    searchingClients.value = false;
  }
};

const handleClientSelection = (clientId) => {
  form.value.client_id = clientId;
};

const openAddClientDialog = (initialName = '') => {
  newClientForm.value = {
    name: initialName,
    phone: '',
    address: '',
  };
  showAddClientDialog.value = true;
};

const saveNewClient = async () => {
  if (!newClientForm.value.name) {
    $q.notify({ type: 'negative', message: t('messages.required') });
    return;
  }

  savingClient.value = true;
  try {
    const response = await api.post('/clients', newClientForm.value);
    const newClient = response.data;
    
    // Add to clients list and select it
    allClients.value.unshift(newClient);
    clientOptions.value = allClients.value;
    form.value.client_id = newClient.id;
    
    showAddClientDialog.value = false;
    $q.notify({ type: 'positive', message: t('sales.clientAddedSuccessfully') });
  } catch (error) {
    const errorMessage = error.response?.data?.message || t('sales.failedToAddClient');
    $q.notify({ type: 'negative', message: errorMessage });
  } finally {
    savingClient.value = false;
  }
};

const loadVanStocks = async () => {
  if (!form.value.distributor_id) {
    vanProducts.value = [];
    vanProductOptions.value = [];
    return;
  }

  try {
    const response = await api.get(`/distributors/${form.value.distributor_id}/stocks`);
    // Map stock items to product format
    const mappedProducts = response.data.map(stock => ({
        ...stock.product,
        id: stock.product_id,
        available_quantity: parseFloat(stock.quantity),
        // Ensure price is accessible (handle both snake and camel case from API)
        currentPrice: stock.product.current_price || stock.product.currentPrice,
        unit: stock.product.unit
    }));

    vanProducts.value = mappedProducts;
    vanProductOptions.value = mappedProducts;
    
    // Also load all products for fallback/reference if needed
    if (allProducts.value.length === 0) {
        const productsResponse = await api.get('/products');
        allProducts.value = productsResponse.data;
    }
  } catch (error) {
    console.error('Failed to load van stocks:', error);
    $q.notify({ type: 'negative', message: t('sales.failedToLoadVanStocks') });
  }
};

const filterProducts = (val, update) => {
  if (val === '') {
    update(() => {
      vanProductOptions.value = vanProducts.value;
    });
    return;
  }

  update(() => {
    const needle = val.toLowerCase();
    vanProductOptions.value = vanProducts.value.filter(
      (p) => p.name.toLowerCase().indexOf(needle) > -1
    );
  });
};

const getVanStockQuantity = (productId) => {
  if (!productId) return 0;
  const product = vanProducts.value.find((p) => p.id === productId);
  return product?.available_quantity || 0;
};

const getProductPrice = (productId, priceType) => {
  if (!productId) return 0;
  
  // First try to find in vanProducts
  let product = vanProducts.value.find((p) => p.id === productId);
  
  // If not found, try allProducts
  if (!product) {
    product = allProducts.value.find((p) => p.id === productId);
  }
  
  if (!product?.currentPrice) return 0;

  switch (priceType) {
    case 'wholesale':
      return parseFloat(product.currentPrice.wholesale_price || 0);
    case 'semi_wholesale':
      return parseFloat(product.currentPrice.semi_wholesale_price || 0);
    case 'retail':
      return parseFloat(product.currentPrice.retail_price || 0);
    default:
      return 0;
  }
};

const handleProductSelection = (item, productId) => {
  item.product_id = productId;
  
  // Auto-set quantity to max available from Van Stock
  const maxQty = getVanStockQuantity(productId);
  item.quantity = maxQty;

  // Ensure selected price type is valid (price > 0)
  const availableTypes = getFilteredPriceTypes(item);
  if (availableTypes.length > 0) {
    const isCurrentValid = availableTypes.some(opt => opt.value === item.price_type_used);
    if (!isCurrentValid) {
      item.price_type_used = availableTypes[0].value;
    }
  }

  // Update price when product is selected
  updatePriceForItem(item);
};

const isProductAlreadySelected = (productId, currentItemId) => {
  if (!productId) return false;
  return form.value.items.some(item => 
    item.product_id === productId && item.id !== currentItemId
  );
};

const getFilteredPriceTypes = (item) => {
  if (!item.product_id) return priceTypeOptions.value;
  
  const filtered = priceTypeOptions.value.filter(opt => {
    const price = getProductPrice(item.product_id, opt.value);
    return price > 0;
  });

  return filtered.length > 0 ? filtered : priceTypeOptions.value;
};

const shouldShowProductOption = (product, currentRowId) => {
  if (!product) return false;
  
  // 1. Hide if Out of Stock
  const stock = getVanStockQuantity(product.id);
  if (stock <= 0) return false;

  // 2. Hide if already selected in another row
  if (isProductAlreadySelected(product.id, currentRowId)) return false;

  return true;
};

const updatePriceForItem = (item) => {
  if (!item.product_id || !item.price_type_used) return;
  
  const newPrice = getProductPrice(item.product_id, item.price_type_used);
  item.selling_price = newPrice;
};


const validateQuantity = (item) => {
  const maxQuantity = getVanStockQuantity(item.product_id);
  const totalReq = (parseFloat(item.quantity) || 0) + (parseFloat(item.promo_quantity) || 0);
  
  if (totalReq > maxQuantity) {
    // If total exceeds, reduce promo first, then quantity
    // Simple logic: Prevent input
    $q.notify({
      type: 'warning',
      message: `Total Quantity (${totalReq}) cannot exceed Van Stock (${formatQuantity(maxQuantity)})`,
      timeout: 2000,
    });
    // Reset to valid state? Hard to guess user intent. 
    // Just clamp the last changed value if possible. 
    // For now, let's clamp promo if it pushes over
    if ( (parseFloat(item.quantity)||0) > maxQuantity ) {
        item.quantity = maxQuantity;
        item.promo_quantity = 0;
    } else {
        item.promo_quantity = Math.max(0, maxQuantity - item.quantity);
    }
  }
};

const formatQuantity = (quantity) => {
  if (!quantity && quantity !== 0) return '0';
  return parseFloat(quantity).toFixed(2);
};

const openDialog = async () => {
  // Reset editing state
  isEditing.value = false;
  editingId.value = null;
  
  if (allProducts.value.length === 0) {
    // Try to load products if not loaded (happens on newly loaded page)
    try {
      const response = await api.get('/products');
      allProducts.value = response.data;
    } catch(e) { console.error(e) }
  }

  // Generate next receipt number
  try {
    const response = await api.get('/sales/next-receipt-number');
    if (response.data.receipt_number) {
        form.value.receipt_number = response.data.receipt_number;
    }
  } catch (error) {
     // If endpoint not ready, just let user type or ignore
  }

  form.value.items = [];
  form.value.distributor_id = null;
  form.value.client_id = null;
  form.value.receipt_date = new Date().toISOString().split('T')[0];
  form.value.receipt_image = null;
  
  // Add one default empty row for convenience
  addItem();
  
  showDialog.value = true;
};

const openPaymentDialog = (receipt) => {
  selectedReceiptForPayment.value = receipt;
  paymentForm.value = {
    amount: 0, 
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'cash',
    reference: '',
    check_date: '',
    note: ''
  };
  showPaymentDialog.value = true;
  fetchPayments(receipt.id);
};

const fetchPayments = async (receiptId) => {
  try {
    const response = await api.get(`/sales/${receiptId}/payments`);
    paymentsValues.value = response.data;
  } catch (error) {
    console.error('Error fetching payments:', error);
    $q.notify({
      color: 'negative',
      message: t('payments.failedToLoadPayments')
    });
  }
};

const savePayment = async () => {
  if (paymentForm.value.amount <= 0) {
    $q.notify({ color: 'negative', message: t('payments.amountMustBePositive') });
    return;
  }
  
  saving.value = true;
  try {
    const payload = { ...paymentForm.value };
    if (!payload.check_date) payload.check_date = null;

    await api.post(`/sales/${selectedReceiptForPayment.value.id}/payments`, payload);
    $q.notify({ color: 'positive', message: t('payments.paymentAdded') });
    fetchPayments(selectedReceiptForPayment.value.id);
    loadReceipts(); 
    
    // Reset form partially
    paymentForm.value.amount = 0;
    paymentForm.value.reference = '';
    paymentForm.value.note = '';
  } catch (error) {
    console.error('Error adding payment:', error);
    $q.notify({ color: 'negative', message: t('payments.failedToAddPayment') });
  } finally {
    saving.value = false;
  }
};

const deletePayment = async (paymentId) => {
  $q.dialog({
    title: t('payments.confirmDeleteTitle'),
    message: t('payments.confirmDeletePayment'),
    cancel: {
      label: t('common.cancel'),
      flat: true
    },
    ok: {
      label: t('common.ok'),
      color: 'negative'
    },
    persistent: true,
  }).onOk(async () => {
    try {
      await api.delete(`/sales-payments/${paymentId}`);
      $q.notify({ color: 'positive', message: t('payments.paymentDeleted') });
      fetchPayments(selectedReceiptForPayment.value.id);
      loadReceipts();
    } catch (error) {
      console.error('Error deleting payment:', error);
      $q.notify({ color: 'negative', message: t('payments.failedToDeletePayment') });
    }
  });
};

const totalPaid = computed(() => {
  return paymentsValues.value.reduce((sum, p) => sum + parseFloat(p.amount), 0);
});

const remainingBalance = computed(() => {
  if (!selectedReceiptForPayment.value) return 0;
  // Calculate total for current receipt
  const total = calculateReceiptTotal(selectedReceiptForPayment.value);
  return Math.max(0, total - totalPaid.value);
});

const getMethodLabel = (value) => {
  const method = paymentMethods.find(m => m.value === value);
  return method ? method.label : value;
};

const getPaymentInfo = (receipt) => {
  const total = calculateReceiptTotal(receipt);
  const totalPaid = parseFloat(receipt.payments_sum_amount || 0);
  const pending = parseFloat(receipt.pending_amount || 0);
  const realPaid = totalPaid - pending;

  // Find pending checks to show in tooltip
  let tooltip = '';
  if (pending > 0 && receipt.payments) {
    const today = new Date();
    const pendingChecks = receipt.payments.filter(p => 
      p.payment_method === 'check' && 
      p.check_date && 
      new Date(p.check_date) > today
    );
    
    if (pendingChecks.length > 0) {
      const dates = pendingChecks.map(p => formatDate(p.check_date)).join(', ');
      tooltip = `${t('payments.checkDue')}: ${dates}`;
    }
  }

  if (total <= 0) return { label: t('sales.paid'), color: 'positive', icon: 'check_circle', tooltip };

  if (realPaid >= total - 0.01) {
    return { label: t('sales.paid'), color: 'positive', icon: 'check_circle', tooltip };
  } else if (totalPaid >= total - 0.01) {
    return { label: t('sales.paidCheck'), color: 'info', icon: 'watch_later', tooltip };
  } else if (totalPaid > 0) {
    const remaining = total - totalPaid;
    const partialTooltip = tooltip ? `${tooltip} | ${t('payments.remaining')}: ${remaining.toFixed(2)} DH` : `${t('payments.remaining')}: ${remaining.toFixed(2)} DH`;
    return { label: t('sales.partial'), color: 'warning', icon: 'timelapse', tooltip: partialTooltip };
  } else {
    return { label: t('sales.unpaid'), color: 'negative', icon: 'cancel', tooltip };
  }
};


const closeDialog = () => {
  showDialog.value = false;
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    receipt_number: '',
    distributor_id: null,
    client_id: null,
    receipt_date: new Date().toISOString().split('T')[0],
    receipt_image: null,
    items: [],
  };
  vanProducts.value = [];
  vanProductOptions.value = [];
};

const addItem = () => {
  form.value.items.push({
    id: Date.now(),
    product_id: null,
    quantity: 0,
    selling_price: 0,
    price_type_used: 'wholesale',
    has_promo: false,
    promo_quantity: 0,
    note: ''
  });
};

const removeItem = (index) => {
  form.value.items.splice(index, 1);
};

const saveSale = async () => {
  saving.value = true;
  try {
    // Validate form
    if (!form.value.distributor_id) {
      $q.notify({ type: 'negative', message: t('sales.selectDistributor') });
      return;
    }

    if (!form.value.client_id) {
      $q.notify({ type: 'negative', message: t('sales.selectCustomer') });
      return;
    }

    if (!form.value.receipt_number) {
      $q.notify({ type: 'negative', message: t('sales.receiptNumberRequired') });
      return;
    }

    // Filter valid items
    const validItems = form.value.items.filter(
      (item) => item.product_id && (item.quantity > 0 || (item.has_promo && item.promo_quantity > 0)) && item.selling_price >= 0
    );

    if (validItems.length === 0) {
      $q.notify({ type: 'negative', message: t('sales.addAtLeastOneItem') });
      return;
    }

    const formData = new FormData();
    formData.append('receipt_number', form.value.receipt_number);
    formData.append('distributor_id', form.value.distributor_id);
    formData.append('client_id', form.value.client_id);
    formData.append('receipt_date', form.value.receipt_date);
    
    if (form.value.receipt_image) {
      formData.append('receipt_image', form.value.receipt_image);
    }
    
    formData.append('items', JSON.stringify(validItems));

    if (isEditing.value) {
      // Update existing receipt
      formData.append('_method', 'PUT');
      await api.post(`/sales/${editingId.value}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      $q.notify({ type: 'positive', message: t('sales.receiptUpdatedSuccessfully') });
    } else {
      // Create new receipt
      await api.post('/sales', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      $q.notify({ type: 'positive', message: t('sales.receiptCreatedSuccessfully') });
    }
    
    closeDialog();
    loadReceipts();
  } catch (error) {
    console.error('Error saving sales receipt:', error);
    const errorMessage = error.response?.data?.message || error.response?.data?.error || t('sales.failedToSaveReceipt');
    $q.notify({ type: 'negative', message: errorMessage });
  } finally {
    saving.value = false;
  }
};

const viewImage = (path) => {
  window.open(`http://localhost:8000/storage/${path}`, '_blank');
};

const calculateReceiptTotal = (receipt) => {
  if (!receipt.details || receipt.details.length === 0) return 0;
  return receipt.details.reduce((sum, detail) => {
    return sum + (parseFloat(detail.quantity) * parseFloat(detail.selling_price));
  }, 0);
};

const formatDate = (date) => {
  if (!date) return '';
  const d = new Date(date);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const getFileIcon = (path) => {
  if (!path) return 'attach_file';
  const ext = path.split('.').pop().toLowerCase();
  if (ext === 'pdf') return 'picture_as_pdf';
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'image';
  return 'attach_file';
};

const getFileIconColor = (path) => {
  if (!path) return 'grey';
  const ext = path.split('.').pop().toLowerCase();
  if (ext === 'pdf') return 'red';
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'blue';
  return 'grey';
};

const getFileTooltip = (path) => {
  if (!path) return 'View File';
  const ext = path.split('.').pop().toLowerCase();
  if (ext === 'pdf') return 'View PDF';
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'View Image';
  return 'View File';
};

const viewReceipt = async (receipt) => {
  loading.value = true;
  try {
    // Fetch full receipt details with relationships
    const response = await api.get(`/sales/${receipt.id}`);
    selectedReceipt.value = response.data;
    
    // Ensure company settings are loaded
    if (!companySettings.value.company_name) {
      await loadSettings();
    }
    
    showReceiptDialog.value = true;
  } catch (error) {
    $q.notify({ type: 'negative', message: t('sales.failedToLoadReceiptDetails') });
  } finally {
    loading.value = false;
  }
};

const loadSettings = async () => {
  try {
    const response = await api.get('/settings');
    companySettings.value = response.data;
  } catch (error) {
    console.error('Failed to load settings:', error);
  }
};

const getLogoUrl = (logoPath) => {
  if (!logoPath) return '';
  // Use same origin to avoid CORS issues
  return `/storage/${logoPath}`;
};

const getPriceTypeLabel = (priceType) => {
  switch (priceType) {
    case 'wholesale': return 'Wholesale';
    case 'semi_wholesale': return 'Semi-Wholesale';
    case 'retail': return 'Retail';
    default: return priceType;
  }
};

const getPriceTypeColor = (priceType) => {
  switch (priceType) {
    case 'wholesale': return 'green';
    case 'semi_wholesale': return 'orange';
    case 'retail': return 'blue';
    default: return 'grey';
  }
};

const saveReceiptAsPDF = async () => {
  if (!selectedReceipt.value) return;
  
  pdfLoading.value = true;
  try {
    const receiptContent = document.getElementById('receipt-content');
    if (!receiptContent) {
      $q.notify({ type: 'negative', message: t('messages.displayContentError') });
      return;
    }

    // Show loading notification
    $q.notify({
      type: 'info',
      message: 'Generating PDF...',
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
    
    // Create PDF
    const pdf = new jsPDF('p', 'mm', 'a4');
    
    // Get sections
    const headerSection = receiptContent.querySelector('.receipt-header-section');
    const infoSection = receiptContent.querySelector('.receipt-info-section');
    const itemsTable = receiptContent.querySelector('.receipt-table');
    const totalsSection = receiptContent.querySelector('.receipt-totals-section');
    const notesSection = receiptContent.querySelector('.receipt-notes-section');
    
    let currentY = marginTop;
    let currentPage = 1;
    
    // Function to add page number
    const addPageNumber = (page, total) => {
      pdf.setFillColor(255, 255, 255);
      pdf.rect(pageWidth - marginRight - 35, pageHeight - 16, 35, 12, 'F');
      pdf.setFontSize(11);
      pdf.setTextColor(40, 40, 40);
      pdf.setFont('helvetica', 'bold');
      const pageText = page + ' / ' + total;
      const textWidth = pdf.getTextWidth(pageText);
      pdf.text(pageText, pageWidth - marginRight - textWidth, pageHeight - 10);
    };
    
    // Function to check if we need a new page
    const checkNewPage = (requiredHeight) => {
      if (currentY + requiredHeight > pageHeight - marginBottom - footerHeight) {
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
    
    // Capture and add items table
    if (itemsTable) {
      const tableCanvas = await html2canvas(itemsTable.closest('.receipt-items-section') || itemsTable, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const tableImg = tableCanvas.toDataURL('image/png');
      const tableHeight = (tableCanvas.height * contentWidth) / tableCanvas.width;
      
      checkNewPage(tableHeight);
      pdf.addImage(tableImg, 'PNG', marginLeft, currentY, contentWidth, tableHeight);
      currentY += tableHeight + 5;
    }
    
    // Capture and add totals section
    if (totalsSection) {
      const totalsCanvas = await html2canvas(totalsSection, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
      });
      const totalsImg = totalsCanvas.toDataURL('image/png');
      const totalsHeight = (totalsCanvas.height * contentWidth) / totalsCanvas.width;
      
      checkNewPage(totalsHeight);
      pdf.addImage(totalsImg, 'PNG', marginLeft, currentY, contentWidth, totalsHeight);
      currentY += totalsHeight + 5;
    }
    
    // Capture and add notes section if exists
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
    const totalPages = currentPage;
    for (let i = 1; i <= totalPages; i++) {
      pdf.setPage(i);
      addPageNumber(i, totalPages);
    }
    
    // Save the PDF
    pdf.save('sales_receipt_' + selectedReceipt.value.receipt_number + '.pdf');
    $q.notify({ type: 'positive', message: t('sales.pdfDownloaded') });
  } catch (error) {
    console.error('Error generating PDF:', error);
    $q.notify({ type: 'negative', message: t('sales.failedToGeneratePdf') });
  } finally {
    pdfLoading.value = false;
  }
};

const editReceipt = async (receipt) => {
  isEditing.value = true;
  editingId.value = receipt.id;
  
  // Set form values from receipt
  form.value = {
    receipt_number: receipt.receipt_number,
    distributor_id: receipt.distributor_id,
    client_id: receipt.client_id,
    receipt_date: formatDate(receipt.receipt_date),
    receipt_image: null, // Can't pre-fill file input
    items: [],
  };
  
  // Load van stocks for distributor
  await loadVanStocks();
  
  // Also load all products for items that might not be in committed list anymore
  try {
    const productsResponse = await api.get('/products');
    allProducts.value = productsResponse.data;
  } catch (error) {
    console.error('Failed to load products:', error);
  }
  
  // Map receipt details to form items
  if (receipt.details && receipt.details.length > 0) {
    form.value.items = receipt.details.map(detail => ({
      id: detail.id,
      product_id: detail.product_id,
      quantity: parseFloat(detail.quantity),
      selling_price: parseFloat(detail.selling_price),
      price_type_used: detail.price_type_used,
      has_promo: parseFloat(detail.promo_quantity) > 0,
      promo_quantity: parseFloat(detail.promo_quantity) || 0,
      note: detail.note || ''
    }));
  }
  
  showDialog.value = true;
};

const confirmDelete = (receipt) => {
  $q.dialog({
    title: t('common.confirm'),
    message: t('messages.confirmDelete') + ` ${receipt.receipt_number}?`,
    cancel: {
      label: t('common.cancel'),
      flat: true
    },
    persistent: true,
    ok: {
      label: t('common.ok'),
      color: 'negative',
      icon: 'delete'
    },
  }).onOk(async () => {
    try {
      await api.delete(`/sales/${receipt.id}`);
      $q.notify({ type: 'positive', message: t('sales.receiptDeletedSuccessfully') });
      loadReceipts();
    } catch (error) {
      console.error('Delete failed:', error);
      const msg = error.response?.data?.message || t('sales.failedToDeleteReceipt');
      $q.notify({ type: 'negative', message: msg, timeout: 5000 });
    }
  });
};

onMounted(() => {
  loadReceipts();
  loadDistributors();
  loadClients();
});
</script>

<style scoped>
.rounded-table {
  border-radius: 12px;
  overflow: hidden;
}

/* Receipt Preview Styles - Similar to Purchase Invoice */
.receipt-content {
  background: #ffffff;
  padding: 40px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.receipt-header-section {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid #1976d2;
}

.receipt-label {
  font-size: 10px;
  color: #666;
  letter-spacing: 1px;
  text-transform: uppercase;
  font-weight: 600;
}

.receipt-value {
  font-size: 14px;
  color: #333;
  font-weight: 600;
}

.company-name {
  font-size: 16px;
  color: #1976d2;
}

.receipt-info-section {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e0e0e0;
}

.receipt-info {
  font-size: 13px;
  line-height: 1.6;
  color: #333;
}

.receipt-items-section {
  margin-bottom: 20px;
}

.receipt-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.receipt-table thead tr {
  background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
  color: white;
}

.receipt-table th {
  padding: 12px 15px;
  text-align: left;
  font-weight: 600;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.receipt-table td {
  padding: 12px 15px;
  border-bottom: 1px solid #e0e0e0;
}

.receipt-table tbody tr:nth-child(even) {
  background: #f8f9fa;
}

.receipt-totals-section {
  margin-top: 20px;
  padding-top: 20px;
}

.totals-box {
  background: #f8f9fa;
  padding: 15px 25px;
  border-radius: 8px;
  min-width: 280px;
}

.total-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #e0e0e0;
  font-size: 14px;
}

.total-row:last-child {
  border-bottom: none;
}

.total-label {
  color: #666;
  font-weight: 500;
}

.total-value {
  font-weight: 600;
  color: #333;
}

.total-value.final {
  font-size: 18px;
  font-weight: bold;
  color: #1976d2;
}

.receipt-notes-section {
  margin-top: 30px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
}

.receipt-notes {
  font-size: 13px;
  color: #333;
  line-height: 1.6;
}

.receipt-actions {
  border-top: 1px solid #e0e0e0;
  padding: 15px 20px;
}

@media print {
  .receipt-content {
    padding: 20px;
  }
}
</style>
