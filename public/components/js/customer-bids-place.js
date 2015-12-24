var productRegexp = /^[a-zA-Z0-9а-яА-Я\'"\\\s]+$/;
var amountRegexp = /^[0-9]+$/;
var priceRegexp = /^-?[0-9]+(\.[0-9]+)?$/;
var customerSessionIdRegexp = /(?:(?:^|.*;\s*)cst_session_id\s*\=\s*([^;]*).*$)|^.*$/;

$(document).ready(function () {
    if (getCustomerSession().length == 0) {
        window.location = '/';
        return;
    }

    updateBalance();
});

function getCustomerSession() {
    return document.cookie.replace(customerSessionIdRegexp, "$1");
}

$("#place-bid").click(function () {
    placeBid()
});
$("#bid-form").on('keypress', function (event) {
    if (event.keyCode == 13) {
        placeBid();
    }
});

$("#logout").click(function () {
    $.post('/api/logout', function () {
        window.location.replace("/")
    }).fail(function () {
        sweetAlert('Server error', 'Unable to log out', 'error');
    });
});


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
        sweetAlert('Success', "Bid has been placed!")
    }).fail(function () {
        sweetAlert('Server error', 'Unable to place the bid', 'error');
    }).done(function () {
        productInput.val('');
        amountInput.val('');
        priceInput.val('');
    });
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

function updateBalance() {
    $.get('/api/customer/profile', function (customer) {
        $('#customer-id').text(customer.id);
        $('#customer-balance').text("$ " + customer.amount);
    });
}