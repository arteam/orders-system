$(document).ready(function () {
    setInterval(function () {
        $.get("/api/bids", findBids)
    }, 2000);
});

function findBids(data) {
    var tbody = $("#bids-table").find("> tbody");
    if (tbody.length > 0) {
        // Clear bids
        tbody.children().remove();
    }

    // Add bids to the list
    for (var i = 0; i < data.length; i++) {
        tbody.append($('<tr>')
            .append($('<td>').append(data[i].id))
            .append($('<td>').append(data[i].product))
            .append($('<td>').append(data[i].amount))
            .append($('<td>').append(data[i].price))
        );
    }
}
