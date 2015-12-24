$(document).ready(function () {
    localStorage['currentBidIds'] = '';
    updateContractorVolume();
    $.get("/api/bids", findBids);
    setInterval(function () {
        $.get("/api/bids", findBids)
    }, 2000);
});

$("#logout").click(function () {
    $.post('/api/logout', function () {
        window.location.replace("/")
    }).fail(function () {
        sweetAlert('Server error', 'Unable to log out', 'error');
    });
});

function findBids(bids) {
    // Work with the bids table body
    var tbody = $("#bids-table").find("tbody");

    // Grab current bid ids
    var currentBidIds = localStorage['currentBidIds'] != '' ? JSON.parse(localStorage['currentBidIds']) : [];
    var maxBidId = Math.max.apply(null, currentBidIds);

    // Add new bids to the list
    var newBidIds = [];

    for (var i = bids.length - 1; i >= 0; i--) {
        var id = parseInt(bids[i].id);
        // If it's a new bid, add to the table with the "fade in" effect
        if (currentBidIds.indexOf(id) < 0) {
            var product = bids[i].product;
            var tr = $('<tr>')
                .attr('id', 'row' + id)
                .append($('<td>').append(id))
                .append($('<td>').append(product))
                .append($('<td>').append(bids[i].amount))
                .append($('<td>').append(bids[i].price))
                .append($('<td>').append(createTakeButton(id, product))
                )
                .fadeIn(2000);
            // If the id is greater than then maximum bid, it's a new bid
            // and we should place it before the top element. Otherwise it's
            // an old bid, that should be added after the last element.
            if (id > maxBidId) {
                tbody.prepend(tr);
            } else {
                tbody.append(tr);
            }
        }
        newBidIds.push(id);
    }

    // Remove taken bids from the table with the "fade out" effect
    for (var j = 0; j < currentBidIds.length; j++) {
        var bidId = currentBidIds[j];
        if (newBidIds.indexOf(bidId) < 0) {
            removeBid(bidId);
        }
    }

    // Sync the current bids ids
    localStorage['currentBidIds'] = JSON.stringify(newBidIds);
}

/**
 * Create a new button and add an event handler that takes the specified bid
 * @param id
 * @param name
 * @returns {*|jQuery}
 */
function createTakeButton(id, name) {
    return $('<button>')
        .attr('type', 'button')
        .addClass('button-take pure-button')
        .append($('<i>').addClass('fa fa-sign-in fa-2x'))
        .click(function () {
            takeBid(id, name)
        });
}
/**
 * Take the specified bid and update the bids table
 * @param id
 * @param name
 */
function takeBid(id, name) {
    $.post('/api/bids/' + id + '/take', function () {
        removeBid(id);
        updateContractorVolume();
        sweetAlert('Success', 'Bid "' + name + '" has been taken!');
    }).fail(function () {
        sweetAlert('Server error', 'Unable to take the bid', 'error');
    });
}

/**
 * Remove the bid from the table
 * @param id
 */
function removeBid(id) {
    $('#row' + id).fadeOut(2000);
}

/**
 * Update the current sales volume
 */
function updateContractorVolume() {
    $.get('/api/contractors/profile', function (contractor) {
        $('#contractor-volume').text('$ ' + contractor.amount);
    });
}