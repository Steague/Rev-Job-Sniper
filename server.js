var app = require('http').createServer(handler),
    io = require('socket.io').listen(app),
    fs = require('fs'),
    psFind = require('ps-find'),
    psKill = require('ps-kill'),
    exec = require('child_process').exec;

var revPid;

// creating the server ( localhost:8000 )
app.listen(8000);

console.log('server listening on localhost:8000');

function getFileStatPromise( file ) {
    return new Promise(function(resolve, reject) {
        if (!file) {
            reject("Invalid file");
            return;
        }

        fs.stat(__dirname + '/' + file, function(err, data) {
            if (err) {
                reject(err);
                return;
            }
            
            resolve(data);
        });
    });
}

function getBotPid( botName ) {
    return new Promise(function(resolve, reject) {
        psFind.find(botName, function( err, results ) {
            if (err) {
                reject(err);
                return;
            }

            if (results.length != 1) {
                reject("Unable to find single process");
                return;
            }

            if (!results[0].hasOwnProperty("name")) {
                reject("Invalid process result");
                return;
            }
         
            resolve(results[0].pid);
        });
    });
}

function killBot( pid ) {
    return new Promise(function(resolve, reject) {
        if (!pid) {
            reject("Invalid PID");
            return;
        }

        psKill.kill(pid, function( err ) {
            if (err) {
                reject(err);
                return;
            }
         
            resolve();
        });
    });
}

function startBot( botName ) {
    return new Promise(function(resolve, reject) {
        if (!botName) {
            reject("Invalid bot name");
            return;
        }

        console.log("debug1");

        exec("php "+botName, function(err, stdout, stderr) {

            console.log(err, stdout, stderr);
            if (err) {
                reject(err);
                return;
            }

            console.log(err, stdout, stderr);

            resolve();
        })
    });
}

getBotPid('test-daemon.php').then(function(result) {
    console.log("LOG", result);
    killBot(result).then(function(data) {
        console.log("Killed the bot");
        startBot('test-daemon.php').then(function(data) {
            console.log("Started the bot");
        }, function(error) {
            console.error("startBot ERROR", error);
        });
    }, function(error) {
        console.error("killBot ERROR", error);
    });
}, function(error) {
    console.error("getBotPid ERROR", error, typeof error);
    if (error == "[Error: No process found.]") {
        console.error("getBotPid Attempting to start bot");

        startBot('test-daemon.php').then(function(data) {
            console.log("Started the bot");
        }, function(error) {
            console.error("startBot ERROR", error);
        });
    }
});

// on server started we can load our client.html page
function handler(req, res) {
    fs.readFile(__dirname + '/client.html', function(err, data) {
        if (err) {
            console.log(err);
            res.writeHead(500);
            return res.end('Error loading client.html');
        }
        res.writeHead(200);
        res.end(data);
    });
}

// creating a new websocket to keep the content updated without any AJAX request
io.sockets.on('connection', function(socket) {
    console.log(__dirname);
    // watching the log file
    fs.watchFile(__dirname + '/rev-log.log', function(curr, prev) {
        // on file change we can read the new log
        getFileStatPromise('rev-log.log').then(function(data) {
            socket.volatile.emit('log-stats', data);
        }, function(error) {
            console.error("ERROR", error);
        });
    });
});
