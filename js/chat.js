$( document ).ready(function() {
    var conn = new WebSocket('ws://webchat.devel.coderscorp.com:8080');
    var user = 'test';
    conn.onopen = function (e) {
        console.log("Connection established!");
    };

    conn.onmessage = function (e) {
        console.log(e.data);
    };


    $('#btn-chat').click(function () {
        console.log('sending ' + $('#btn-input').val())
        var text = $('#btn-input').val();
        var msg = {
            'user': user,
            'text': text,
            'time': moment().format('hh:mm a')
        };
        //updateMessages(msg);
        conn.send(JSON.stringify(msg));

        $('#btn-input').val('');
    });


});