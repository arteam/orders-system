var productRegexp = /^[a-zA-Z0-9а-яА-Я\'"\\\s]+$/;
var amountRegexp = /^[0-9]+$/;
var priceRegexp = /^-?[0-9]+(\.[0-9]+)?$/;
var customerSessionIdRegexp = /(?:(?:^|.*;\s*)cst_session_id\s*\=\s*([^;]*).*$)|^.*$/;

$(document).ready(function () {
    // Redirect to the front page, if session is not exist
    if (getCustomerSession().length == 0) {
        window.location = '/';
        return;
    }

    updateBalance();
});


// Place a bid by a click on the button by pressing "Enter"
$("#place-bid").click(placeBid);
$("#bid-form").on('keypress', function (event) {
    if (event.keyCode == 13) {
        placeBid();
    }
});

// Logout from the current session
$("#logout").click(function () {
    $.post('/api/logout', function () {
        window.location.replace("/");
    }).fail(function () {
        sweetAlert('Server error', 'Unable to log out', 'error');
    });
});

/**
 * Place a new bid to the server
 */
function placeBid() {
    var productInput = $("#product");
    var product = productInput.val().trim();
    if (!validateProduct(product)) {
        return;
    }

    var amountInput = $("#amount");
    var textAmount = amountInput.val().trim();
    if (!validateAmount(textAmount)) {
        return;
    }

    var priceInput = $("#price");
    var textPrice = priceInput.val().trim();
    if (!validatePrice(textPrice)) {
        return;
    }

    $.post('/api/bids/place', JSON.stringify({
        "product": product,
        "amount": parseInt(textAmount),
        "price": parseFloat(textPrice).toFixed(2)
    })).success(function () {
        sweetAlert('Success', "Bid has been placed!");
        productInput.val('');
        amountInput.val('');
        priceInput.val('');
    }).fail(function () {
        sweetAlert('Server error', 'Unable to place the bid', 'error');
    })
}

/**
 * Validates that product is set and contains safe symbols
 * @param product
 * @returns {boolean}
 */
function validateProduct(product) {
    if (product.length == 0) {
        sweetAlert('Validation error', 'Product name is not set', 'error');
        return false;
    }
    if (product.length > 32) {
        sweetAlert('Validation error', 'Product name is too big', 'error');
        return false;
    }
    console.log(product);
    if (!productRegexp.test(product)) {
        sweetAlert('Validation error', 'Wrong product name format', 'error');
        return false;
    }
    return true;
}

/**
 * Validates that amount is a number between 1 and 1000
 * @param textAmount
 * @returns {boolean}
 */
function validateAmount(textAmount) {
    if (textAmount.length == 0) {
        sweetAlert('Validation error', 'Amount is not set', 'error');
        return false;
    }
    if (!amountRegexp.test(textAmount)) {
        sweetAlert('Validation error', 'Amount is not a number', 'error');
        return false;
    }
    var amountAsNumber = parseInt(textAmount);
    if (amountAsNumber < 1) {
        sweetAlert('Validation error', 'Amount should be greater than 0', 'error');
        return false;
    }
    if (amountAsNumber > 1000) {
        sweetAlert('Validation error', 'Amount should be less than 100', 'error');
        return false;
    }
    return true;
}

/**
 * Validates that price is a real number between 0 and 10000
 * @param textPrice
 * @returns {boolean}
 */
function validatePrice(textPrice) {
    if (textPrice.length == 0) {
        sweetAlert('Validation error', 'Price is not set', 'error');
        return false;
    }
    if (!priceRegexp.test(textPrice)) {
        sweetAlert('Validation error', 'Price is not a real number', 'error');
        return false;
    }
    var priceAsNumber = parseFloat(textPrice);
    if (priceAsNumber <= 0) {
        sweetAlert('Validation error', 'Price should be greater than 0', 'error');
        return false;
    }
    if (priceAsNumber > 10000) {
        sweetAlert('Validation error', 'Price should be less or equal than 10000', 'error');
        return false;
    }
    return true;
}

/**
 * Updates the current customer's balance and id
 */
function updateBalance() {
    $.get('/api/customer/profile', function (customer) {
        $('#customer-id').text(customer.id);
        $('#customer-balance').text("$ " + customer.amount);
    });
}

/**
 * Helper function to get the current session from the `Cookie` header
 * @returns {string}
 */
function getCustomerSession() {
    return document.cookie.replace(customerSessionIdRegexp, "$1");
}