/**
 * ConcordPay scripts.
 *
 * @package 'concordpay-button'
 */
(function (i18n) {
	const __ = i18n.__;

	const cpbPopup = document.querySelector('#cpb_popup');
	const cpbCheckoutForm = document.querySelector('#cpb_checkout_form');
	// Shortcode parameters.
	const productNameField = document.querySelector('.js-cpb-product-name');
	const productPriceField = document.querySelector('.js-cpb-product-price');
  // Client info.
	const clientNameField = document.querySelector('.js-cpb-client-name');
	const clientPhoneField = document.querySelector('.js-cpb-client-phone');
	const clientEmailField = document.querySelector('.js-cpb-client-email');
	const clientAmount = document.querySelector('.js-cpb-product-price');
	const clientAmountWrapper = document.querySelector('.js-cpb-product-price-wrapper');
	// Required fields when making a purchase.
	const requiredFields = [clientNameField, clientPhoneField, clientEmailField];

	// ConcordPay payment button listener.
	window.addEventListener(
		'click',
		(event) => {
			if (event.target.dataset.type !== 'cpb_submit') {
				return;
			}
			event.preventDefault();
			if (typeof cpbPopup !== 'undefined' && cpbPopup && productNameField && productPriceField) {
				productNameField.value = event.target.dataset.name;
				productPriceField.value = event.target.dataset.price;
				if (requiredFields.every(element => element === null) && productPriceField.value.toLowerCase() !== 'custom') {
					// if 'CPB_MODE_NONE' enabled.
					cpbCheckoutForm.dispatchEvent(new Event('submit'));
				} else {
					// Other modes. Open popup window.
					if (productPriceField.value.toLowerCase() === 'custom') {
						productPriceField.value = 0;
						clientAmountWrapper.classList.remove('cpb-popup-field-hidden');
					}
					cpbPopup.classList.add('open');
				}
			}
		}
	);

	// Popup window close button handler.
	const cpbClose = document.querySelector('.cpb-popup-close');
	if (typeof cpbClose !== 'undefined' && cpbClose) {
		cpbClose.onclick = event => {
			event.preventDefault();
			resetFormFields();
			if (clientAmountWrapper.classList.contains('cpb-popup-field-hidden') !== true) {
				clientAmountWrapper.classList.add('cpb-popup-field-hidden');
			}
			cpbPopup.classList.remove('open');
			resetValidationMessages();
		};
	}

	// Checkout form handler (popup window).
	if (typeof cpbCheckoutForm !== 'undefined' && cpbCheckoutForm) {
		cpbCheckoutForm.onsubmit = event => {
			event.preventDefault();
			if (isFormHasNoErrors()) {
				validateCheckoutForm(event)
			}
		};
		// Event listeners for separate form fields.
		requiredFields.push(clientAmount);
		requiredFields.map(
			field => {
				if (typeof field !== 'undefined' && field) {
					field.onchange = event => validateCheckoutField(event);
				}
			}
		);
		requiredFields.pop();
	}

	/**
	 * Validate a form field if its value has changed.
	 *
	 * @param event
	 */
	function validateCheckoutField(event) {
		const fieldId = event.target.id;
		const fieldValue = event.target.value;

		switch (fieldId) {
			case 'cpb_client_name':
				validateName(fieldValue);
				break;
			case 'cpb_phone':
				validatePhone(fieldValue);
				break;
			case 'cpb_email':
				validateEmail(fieldValue);
				break;
			case 'cpb_product_price':
				validateAmount(fieldValue);
		}
	}

	/**
	 * Validate name field.
	 *
	 * @param value
	 */
	function validateName(value) {
		const errorMessage = document.querySelector('.js-cpb-error-name');
		if (value.trim().length !== 0) {
			removeValidationMessage(errorMessage);
			return;
		}

		errorMessage.innerHTML = __('Invalid name', 'concordpay-button');
		highlightNearestInput(errorMessage);
	}

	/**
	 * Validate phone field.
	 *
	 * @param value
	 */
	function validatePhone(value) {
		const errorMessage = document.querySelector('.js-cpb-error-phone');
		if (value.replace(/\D/g, '').length >= 10) {
			removeValidationMessage(errorMessage);
			return;
		}

		errorMessage.innerHTML = __('Invalid phone number', 'concordpay-button');
		highlightNearestInput(errorMessage);
	}

	/**
	 * Validate email field.
	 *
	 * @param value
	 */
	function validateEmail(value) {
		const errorMessage = document.querySelector('.js-cpb-error-email');
		const emailPattern = /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
		if (String(value).toLowerCase().match(emailPattern)) {
			removeValidationMessage(errorMessage);
			return;
		}

		errorMessage.innerHTML = __('Invalid email', 'concordpay-button');
		highlightNearestInput(errorMessage);
	}

	/**
	 * Validate product price (amount) field.
	 *
	 * @param value
	 */
	function validateAmount(value) {
		const errorMessage = document.querySelector('.js-cpb-error-product-price');
		if (value.trim().length !== 0 && !isNaN(value) && !isNaN(parseFloat(value)) && parseFloat(value) > 0) {
			removeValidationMessage(errorMessage);
			return;
		}

		errorMessage.innerHTML = __('Invalid amount', 'concordpay-button');
		highlightNearestInput(errorMessage);
	}

	/**
	 * Checkout form validator.
	 *
	 * @param event
	 */
	function validateCheckoutForm(event) {
		event.preventDefault();

		const request = new XMLHttpRequest();
		request.open('POST', cpb_ajax.url, true);
		request.send(new FormData(cpbCheckoutForm));

		request.onload = function () {
			if (this.status >= 200 && this.status < 400) {
				const response = this.response;
				if (isJson(response)) {
					// Response has validation errors. Add validation errors on form.
					resetValidationMessages();
					const errors = JSON.parse(response);
					for (let error in errors) {
						if (errors.hasOwnProperty(error)) {
							let errorMessage = document.querySelector('.js-cpb-error-' + error);
							errorMessage.innerHTML = errors[error];
							highlightNearestInput(errorMessage);
						}
					}
				} else if (event.type === 'change') {
					resetValidationMessages();
				} else {
					// Success.
					cpbPopup.classList.remove('open');
					// Waiting for the end of the popup closing animation.
					const waitEndAnimation = setTimeout(
						() => {
							lockBody();
							const cpbCheckoutFormWrapper = document.querySelector('.cpb-popup-content');
							cpbCheckoutFormWrapper.innerHTML = response;
							document.querySelector('#cpb_payment_form').submit();
						},
						800
					);
				}
			} else {
				// Fail.
				console.log('ConcordPay plugin validate request error');
			}
		}

		request.onerror = function () {
			console.log('ConcordPay plugin error');
		}
	}

	/**
	 * Blocking the scroll page body while redirecting to the payment page.
	 */
	function lockBody() {
		document.body.classList.add('cpb-lock');
	}

	/**
	 * Checking if json was received in the response.
	 *
	 * @param str
	 * @returns {boolean}
	 */
	function isJson(str) {
		try {
			JSON.parse(str);
		} catch (e) {
			return false;
		}
		return true;
	}

	/**
	 * Reset all validation messages in form.
	 */
	function resetValidationMessages() {
		const messages = document.querySelectorAll('[class^=js-cpb-error-]');
		messages.forEach(message => message.innerHTML = '');

		const fields = document.querySelectorAll('.cpb-popup-input');
		fields.forEach(field => field.classList.remove('cpb-not-valid'));
	}

	/**
	 * Reset form fields to empty.
	 */
	function resetFormFields() {
		productNameField.value = '';
		productPriceField.value = '';
		cpbCheckoutForm.reset();
	}

	/**
	 * Reset validation message for specify field.
	 *
	 * @param elem
	 */
	function removeValidationMessage(elem) {
		elem.innerHTML = '';
		offHighlightNearestInput(elem);
	}

	/**
	 * Add warning selection on nearest input field.
	 *
	 * @param elem
	 */
	function highlightNearestInput(elem) {
		let input = elem.parentNode.querySelector('.cpb-popup-input');
		input.classList.add('cpb-not-valid');
	}

	/**
	 * Remove warning selection from the nearest input field.
	 *
	 * @param elem
	 */
	function offHighlightNearestInput(elem) {
		let input = elem.parentNode.querySelector('.cpb-popup-input');
		input.classList.remove('cpb-not-valid');
	}

	/**
	 * Check that form has no errors.
	 *
	 * @returns {boolean}
	 */
	function isFormHasNoErrors() {
		const hasErrors = cpbCheckoutForm.querySelectorAll('.cpb-not-valid');

		return hasErrors.length === 0;
	}

}(
	window.wp.i18n
));
