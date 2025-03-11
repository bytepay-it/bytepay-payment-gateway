jQuery(document).ready(function ($) {
  // Sanitize the BYTEPAY_PAYMENT_CODE parameter
  const BYTEPAY_PAYMENT_CODE =     
    typeof bytepayParams.BYTEPAY_PAYMENT_CODE === 'string' ? $.trim(bytepayParams.BYTEPAY_PAYMENT_CODE) : ''

  function bytepay_toggleSandboxFields() {
    // Ensure BYTEPAY_PAYMENT_CODE is sanitized and valid
    if (BYTEPAY_PAYMENT_CODE) {
      // Check if sandbox mode is enabled based on checkbox state
      const bytepay_sandboxChecked = $(
        '#woocommerce_' + $.escapeSelector(BYTEPAY_PAYMENT_CODE) + '_sandbox'
      ).is(':checked')

      // Selectors for sandbox and production key fields
      const bytepay_sandboxSelector =
        '.' + $.escapeSelector(BYTEPAY_PAYMENT_CODE) + '-sandbox-keys'
      const bytepay_productionSelector =
        '.' + $.escapeSelector(BYTEPAY_PAYMENT_CODE) + '-production-keys'

      // Show/hide sandbox and production key fields based on checkbox
      $(bytepay_sandboxSelector).closest('tr').toggle(bytepay_sandboxChecked)
      $(bytepay_productionSelector).closest('tr').toggle(!bytepay_sandboxChecked)
    }
  }

  // Initial toggle on page load
  bytepay_toggleSandboxFields()

  // Toggle on checkbox change
  $('#woocommerce_' + $.escapeSelector(BYTEPAY_PAYMENT_CODE) + '_sandbox').change(
    function () {
      bytepay_toggleSandboxFields()
    }
  )
})
