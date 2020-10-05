<!DOCTYPE html>
<html>
    <head>
        <title>Test form for taxon name lookup</title>
        <script>
            function update(q){
                let results_area = document.getElementById("results_display");

                fetch('suggest.php?q=' + q)
                    .then(response => response.json())
                    .then(data => {
                        
                        console.log(data);
                        
                        results_area.innerHTML = "";

                        for (let index = 0; index < data.length; index++) {
                            
                            const doc = data[index];
                            var li = document.createElement("li"); 
                            results_area.appendChild(li);

                            var span = document.createElement('span');
                            li.appendChild(span);
                            span.innerHTML = doc.search_display;

                            var a = document.createElement("a");
                            li.appendChild(a);
                            a.innerHTML = '&nbsp;WFO ↗';
                            a.href= "http://www.worldfloraonline.org/taxon/" + doc.taxonID_s;                            
                            
                        }

                        return data;

                    });
            }
        </script>
    </head>
    <body>
    <h1>Taxon Lookup Tester</h1>
        <h2>Form</h2>
        <form>
            <input type="text" size="100" onKeyUp="update(this.value)" />
        </form>
        <ul id="results_display" >Search is case sensitive to differentiate genera from epithets.</ul>

    
    </body>
</html>