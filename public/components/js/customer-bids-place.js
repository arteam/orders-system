var productRegexp = new RegExp('^[a-zA-Z0-9а-яА-Я\'"\s]+$');

$(document).ready(function () {
    // TODO checkIfSessionExists
});

$("#place-bid").click(function () {
    var product = $("#product").val();
    if (!validateProduct(product)) {
        return;
    }

    var amount = $("#amount").val();
    var price = $("#price").val();
    $.post('/api/bids/place', JSON.stringify({
        "product": product,
        "amount": amount,
        "price": price
    })).success(function () {
        alert("Placed!")
    }).fail(function () {
        alert("Error");
    });
});

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