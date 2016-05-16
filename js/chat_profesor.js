$( document ).ready(function() {
    var conn = new WebSocket('ws://webchat.devel.coderscorp.com:8080');
    conn.onopen = function (e) {
        register();
        console.log("Connection established!");
    };

    conn.onmessage = function (e) {
        //console.log(e.data);
        var msg = JSON.parse(e.data);
        if(typeof msg.error != 'undefined' || msg.error === true)
        {
            alert(msg.msg);
            return;
        }
        switch(msg.opcode)
        {
            case 2: //new question
                $("#chat_panel").append(msg.html);
                $('#answer_id_'+msg.qid).click(function(){
                    inform_server(msg.qid);
                });
                break;
            case 3: //new answer
                $(msg.html).insertAfter('#question_id_'+msg.qid);
                $('#answer_id_'+msg.qid).prop('checked', false);
                $('#answer_id_'+msg.qid).parent().hide();

                break;
            case 4: //intent to answer
               //console.log(msg);
                for ( var key in msg.list)
                {
                    var str="";
                    for( var name in msg.list[key])
                        str=str+" "+name;
                    $('#intent_id_'+key).html("Answering: "+str);
                }
                break;
        }

    };


    $('#btn-chat').click(function () {
        //console.log('sending ' + $('#btn-input').val())
        var text = $('#btn-input').val();
        var qid =  $('#myform input[name=question]:checked').val();

        /*if(qid==='undefined')
        {
            alert('You must select a question to answer first');
            return;
        }*/

        var msg = {
            'opcode': 3,// new question
            'nick': user,
            'type': 1,
            'text': text,
            'qid' : qid,
            'time': moment().format('hh:mm a')
        };
        //updateMessages(msg);
        //if(!registered) register();
        conn.send(JSON.stringify(msg));

        $('#btn-input').val('');
    });

    $('#btn-deselect').click(function() {
        var qid =  $('#myform input[name=question]:checked').val();
        if(qid !='undefined')
        {
            var msg = {
                'opcode': 5,// intent clear
                'nick': user,
                'type': 1,
                'qid' : qid,
            };
            conn.send(JSON.stringify(msg));
        }
        $('#myform input[name="question"]').prop('checked', false);
        $('#btn-input').val('');
    });
    function register()
    {

        var msg = {
            'opcode': 1,//new user
            'nick': user,
            'type': 1,//profesor
        };
        conn.send(JSON.stringify(msg));
    }
    function inform_server(id)
    {
        if($('#answer_id_'+id).is(':checked')) {
            var msg = {
                'opcode': 4, //intent to answer
                'nick': user,
                'type': 1,
                'qid': id
            };
            //console.log(msg);
            conn.send(JSON.stringify(msg));
        }
    }
});
