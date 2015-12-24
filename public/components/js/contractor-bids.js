$(document).ready(function () {
    localStorage['currentBidIds'] = '';
    $.get("/api/bids", findBids);
    setInterval(function () {
        $.get("/api/bids", findBids)
    }, 2000);
});

function findBids(bids) {
    // Work with the bids table body
    var tbody = $("#bids-table").find("tbody");

    // Grab current bid ids
    var currentBidIds = localStorage['currentBidIds'] != '' ? JSON.parse(localStorage['currentBidIds']) : [];

    // Add new bids to the list
    var newBidIds = [];
    for (var i = 0; i < bids.length; i++) {
        var id = parseInt(bids[i].id);
        // If it's a new bid, add to the table with the "fade in" effect
        if (currentBidIds.indexOf(id) < 0) {
            tbody.append($('<tr>')
                .attr('id', 'row' + id)
                .append($('<td>').append(id))
                .append($('<td>').append(bids[i].product))
                .append($('<td>').append(bids[i].amount))
                .append($('<td>').append(bids[i].price))
                .fadeIn(2000));
        }
        newBidIds.push(id);
    }

    // Remove taken bids from the table with the "fade out" effect
    for (var j = 0; j < currentBidIds.length; j++) {
        var bidId = currentBidIds[j];
        if (newBidIds.indexOf(bidId) < 0) {
            $('#row' + bidId).fadeOut(2000);
        }
    }

    // Sync the current bids ids
    localStorage['currentBidIds'] = JSON.stringify(newBidIds);
}
