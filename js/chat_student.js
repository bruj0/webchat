$( document ).ready(function() {
    var conn = new WebSocket('ws://webchat.devel.coderscorp.com:8080');
    conn.onopen = function (e) {
        register();
        console.log("Connection established!");
    };

    conn.onmessage = function (e) {
        console.log(e.data);
        var msg = JSON.parse(e.data);
        switch(msg.opcode)
        {
            case 2: //new question
                $("#chat_panel").append(msg.html);
                break
            case 3://new answer
                $(msg.html).insertAfter('#question_id_'+msg.qid);
                break;
        }

    };


    $('#btn-chat').click(function () {
        console.log('sending ' + $('#btn-input').val())
        var text = $('#btn-input').val();
        if(text=='')
        {
            alert('Please write a question first');
            return;
        }
        var msg = {
            'opcode': 2,// new question
            'nick': user,
            'type': 2,
            'text': text,
            'time': moment().format('hh:mm a')
        };
        //updateMessages(msg);
        //if(!registered) register();
        conn.send(JSON.stringify(msg));

        $('#btn-input').val('');
    });

function register()
{

    var msg = {
        'opcode': 1,//new user
        'nick': user,
        'type': 2,
    };
    //updateMessages(msg);
    conn.send(JSON.stringify(msg));
    registered=true;
}
});