$(document).ready(function () {
    localStorage.setItem('oldBidIds', []);
    $.get("/api/bids", findBids);
    setInterval(function () {
        $.get("/api/bids", findBids)
    }, 2000);
});

function findBids(data) {
    var tbody = $("#bids-table").find("> tbody");

    var oldBidIds = localStorage.getItem('oldBidIds');
    if (oldBidIds == null) {
        oldBidIds = [];
    }

    var ids = [];

    // Add new bids to the list
    for (var i = 0; i < data.length; i++) {
        var id = data[i].id;
        console.log(id);
        if (oldBidIds.indexOf(id) < 0) {
            var tr = $('<tr>')
                .append($('<td>').append(id))
                .append($('<td>').append(data[i].product))
                .append($('<td>').append(data[i].amount))
                .append($('<td>').append(data[i].price));
            tr.hide();
            tr.fadeIn(2000);
            tbody.append(tr);
        }
        ids.push(id);
    }

    localStorage.setItem('oldBidIds', ids);
}
