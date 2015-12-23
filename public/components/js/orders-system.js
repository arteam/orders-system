$("#registry-contractor").click(function () {
    $.post('api/contractors/register', function () {
        window.location.replace("contractor/bids");
    });
});

$("#registry-customer").click(function () {
    $.post('api/customers/register', function () {
        window.location.replace("customer/bids/place");
    });
});

