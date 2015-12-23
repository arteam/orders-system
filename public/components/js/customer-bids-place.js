var productRegexp = new RegExp('^[a-zA-Z0-9а-яА-Я\'"\s]+$');
var amountRegexp = new RegExp('^[0-9]+$');

$(document).ready(function () {
    // TODO checkIfSessionExists
});

$("#place-bid").click(function () {
    var product = $("#product").val();
    if (!validateProduct(product)) {
        return;
    }

    var textAmount = $("#amount").val();
    if (!validateAmount(textAmount)) {
        return;
    }

    var price = $("#price").val();
    $.post('/api/bids/place', JSON.stringify({
        "product": product,
        "amount": parseInt(textAmount),
        "price": price
    })).success(function () {
        alert("Placed!")
    }).fail(function () {
        alert("Error");
    });
});

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