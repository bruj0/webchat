# webchat
Webchat demo with Websockets

This webchat will allow a group of students to make questions only and professors to answer one question only.
Each one has a login id.

## Technologies used

* Websocket:  http://socketo.me/
* Memcached: https://memcached.org/
* Fatfreeframework: http://fatfreeframework.com/home
* Twitter's Bootstrap: 
* composer: getcomposer.org

## Problems and solutions

* One of the biggest problems with websockets is that the server is separated from the web server.
This causes issues when the client refreshes the webpage because it will try to register a new client.

The solution to this was keeping in memory an array with userIDs and websocket Ids.

When the client refreshes the page and sends a new registration, the websocket server will look in this array and 
if found replace the websocketID instead of generating a new user registration.

* Data integrity and race conditions.
* 
When developing a server that can answer to multiple clients asynchronously there is always a risk of race conditions.
In this case when a Professor answer a question at the same time or very close that another does.

We solved this by using the class Memcached and the function "cas" which performs a "check and set" operation, 
so that the item will be stored only if no other client has updated it since it was last fetched by this client.

## Testing and validation
I tested the solution by loging a student in chrome, using the incognito for a second student, login in with Mozilla with a professor and using the incognito mode on this browser for another professor.

This is because we use sessions from PHP that store the ID on a cookie and all incognito windows share the same cookies.

## Performance under heavy load
Care was taken to not use heavy lookup mechanism so that everything is found with hash tables,
ie:

$tmp['users'][$nick]['websocket_id'] = $from;
$tmp['websocket'][$from]=&$tmp['users'][$nick];

Here we link the users record using his ID ($nick) with his websocket_id and his websocket ID ($from) to his user record.
This way we can do hash lookups with his ID or websocket ID in one try.

Since everything is stored on memory and the search are done by hash tables it reaches the maximum possible performance.



