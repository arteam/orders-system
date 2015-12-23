$("#registry-contractor").click(function () {
    $.post('api/contractors/register', function () {
        window.location.replace("contractor/bids");
    });
});

