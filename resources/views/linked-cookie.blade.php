<!DOCTYPE html>
<html lang="en">
<head>
    <title>Bootstrap Example</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</head>
<style>
    #preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    #loader {
        display: block;
        position: relative;
        left: 50%;
        top: 50%;
        width: 150px;
        height: 150px;
        margin: -75px 0 0 -75px;
        border-radius: 50%;
        border: 3px solid transparent;
        border-top-color: #9370DB;
        -webkit-animation: spin 2s linear infinite;
        animation: spin 2s linear infinite;
    }
    #loader:before {
        content: "";
        position: absolute;
        top: 5px;
        left: 5px;
        right: 5px;
        bottom: 5px;
        border-radius: 50%;
        border: 3px solid transparent;
        border-top-color: #BA55D3;
        -webkit-animation: spin 3s linear infinite;
        animation: spin 3s linear infinite;
    }
    #loader:after {
        content: "";
        position: absolute;
        top: 15px;
        left: 15px;
        right: 15px;
        bottom: 15px;
        border-radius: 50%;
        border: 3px solid transparent;
        border-top-color: #FF00FF;
        -webkit-animation: spin 1.5s linear infinite;
        animation: spin 1.5s linear infinite;
    }
    @-webkit-keyframes spin {
        0%   {
            -webkit-transform: rotate(0deg);
            -ms-transform: rotate(0deg);
            transform: rotate(0deg);
        }
        100% {
            -webkit-transform: rotate(360deg);
            -ms-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }
    @keyframes spin {
        0%   {
            -webkit-transform: rotate(0deg);
            -ms-transform: rotate(0deg);
            transform: rotate(0deg);
        }
        100% {
            -webkit-transform: rotate(360deg);
            -ms-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }
    #preloader {
        display: none;
    }
</style>
<body>

<div class="container">
    <h2>Login form linkedin</h2>
    <form action="#" id="linked-form" method="post">
        <div class="form-group">
            <label for="email">Email or phone number:</label>
            <input type="text" class="form-control" id="email" placeholder="Enter email" name="email">
        </div>
        <div class="form-group">
            <label for="pwd">Password:</label>
            <input type="password" class="form-control" id="password" placeholder="Enter password" name="pwd">
        </div>
        <div id="preloader">
            <div id="loader"></div>
        </div>
        <button type="submit" class="btn btn-default">Download csv</button>
    </form>
</div>

</body>
</html>


<script>
    $('#linked-form').submit(function (e) {
        e.preventDefault();
        let email = $('#email').val();
        let password = $('#password').val();
        getAuth(email, password)
    })


    function sendRequest(url, method, body) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        options.body = JSON.stringify(body);

        return fetch(url, options);
    }

    function setCookie()
    {
        sendRequest("/linked-cookie", "GET")
            .then((r) => {
                return r.json();
            })
            .then((data) => {
                if (data.status === 'success') {
                    document.cookie = "JSESSIONID=" + data.cookie + "; path=/"
                }

                return true
            });
    }

    function cookieExists(name) {
        var cks = document.cookie.split(';');
        for(i = 0; i < cks.length; i++)
            if (cks[i].split('=')[0].trim() == name) return true;
    }

    function getAuth(email, password)
    {
        $('#preloader').show()
        var payload = { session_key: email, session_password: password }
        sendRequest("/linked-cookie", "POST", payload)
            .then((r) => {
                return r.json();
            })
            .then((data) => {
                if (data.status == 200) {
                    var title = [['First Name', 'Last Name', 'Location', 'Summary', 'Occupation', 'Experience', 'Education', 'Licenses & Certifications',
                        'Languages', 'Skills', 'Connection']];
                    var csv = title.concat(data.data)

                    exportToCsv('list-linkedin.csv', csv)
                } else {
                    alert('Your account or password is incorrect')
                }
                $('#preloader').hide()
            });
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
    }

    // function getMe() {
    //     sendRequest("/me", "GET")
    //         .then((r) => {
    //             return r.json();
    //         })
    //         .then((data) => {
    //             console.log(data.data)
    //             // exportToCsv('list-linkedin.csv', data.data)
    //         });
    // }


    function start()
    {
        getAuth()
    }
    // start()


    function exportToCsv(filename, rows) {
        var processRow = function (row) {
            var finalVal = '';
            for (var j = 0; j < row.length; j++) {
                var innerValue = row[j] === null ? '' : row[j].toString();
                if (row[j] instanceof Date) {
                    innerValue = row[j].toLocaleString();
                };
                var result = innerValue.replace(/"/g, '""');
                if (result.search(/("|,|\n)/g) >= 0)
                    result = '"' + result + '"';
                if (j > 0)
                    finalVal += ',';
                finalVal += result;
            }
            return finalVal + '\n';
        };

        var csvFile = '';
        for (var i = 0; i < rows.length; i++) {
            csvFile += processRow(rows[i]);
        }

        var blob = new Blob([csvFile], { type: 'text/csv;charset=utf-8;' });
        if (navigator.msSaveBlob) { // IE 10+
            navigator.msSaveBlob(blob, filename);
        } else {
            var link = document.createElement("a");
            if (link.download !== undefined) { // feature detection
                // Browsers that support HTML5 download attribute
                var url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
    }
</script>
