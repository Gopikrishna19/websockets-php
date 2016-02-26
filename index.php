<html>
    <body>
        <script>
            var ws = new WebSocket("ws://localhost:2428");
            ws.onopen = function (e) {
                console.log("Connected to server");
            }
            ws.onmessage = function (e) {
                console.log(typeof e.data);
                console.log(e.data.toString());
                console.log(JSON.parse(e.data.toString()));
            }
        </script>
    </body>
</html>