$(document).ready(function () {
    // TODO checkIfSessionExists
});

$("#place-bid").click(function () {
    var product = $("#product").val();
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