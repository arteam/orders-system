$(document).ready(function () {
    $.get("/api/bids", findBids)
});

function findBids(data) {
    // Clear bids
    var bidsList = $("#bids-list");
    bidsList.empty();

    // Show bids in a list
    for (var i = 0; i < data.length; i++) {
        var label = $('<label/>')
            .append(data[i].product)
            .append(" | ")
            .append(data[i].amount);
        var li = $('<li>')
            .append(label);
        bidsList.append(li);
    }
}
