<h2>Startseite</h2>
<p>Wilkommen auf meiner kleinen Seite. Ich hoffe, du findest, was du suchst.</p>

<h2>Spenden für die Kommune (in eigener Sache)</h2>
<p>Hallo, ihr Lieben, <br> bitte spendet für die Startfinanzierung der antifaschistischen Kommune! Wir brauchen Geld, um das Gebäude zu kaufen, notwendige Anschaffungen und Umbauten machen zu können. Bitte helft, um das Projekt zu ermöglichen. Wir werden Veranstaltungen und auch Werkstätten machen. Wäre echt süß, wenn ihr helft.</p>

<h3>Unser GoFundMe</h3>
<p>
Hallo, wir sind 7 Menschen (ich bin eine von diesen), die ihr Projekt einer Kommune verwirklichen wollen. Wir wollen einen Hof in Ballerstedt, Sachsen Anhalt kaufen und dort wohnen.<br>
Wir wollen die Finanzierung sichern, eine kleine Holzwerkstatt einrichten und auch Leben in das Dorf bringen in dem wir Konzerte, Festivals und andere öffentliche Veranstaltungen organisieren. Wir sind klar antifaschistisch und wollen diese Idee auch aufs Land bringen. Dazu benötigen wir eure Hilfe, sonst können wir das Projekt nicht starten.<br>
Wir brauchen die Startfinanzierung, um das Gelände kaufen zu können, einige wichtige Maschinen kaufen zu können und nötige Umbaumaßnahmen durchführen zu können. <br>
<br><br>
Helft uns auf GoFundMe!
<br>
Gerne diesen Aufruf weiterverteilen!
</p>
<p><a href="https://www.gofundme.com/f/startfinanzierung-unserer-kommune">GoFundMe- Link</a></p>
<p><img style="max-height:300px;margin-left:3vw;" src="assets/kommune-qr.png"></p>
<p>
Vielen Dank, alle!
</p>


<h3>Die letzten Änderungen</h3>
<?php

function teaser($filename) {
    $filepath = __DIR__ . '/' . $filename; // Construct the full file path

    if (file_exists($filepath)) {
        $lines = file($filepath);
        if ($lines !== false) {
            $firstFiveLines = array_slice($lines, 0, 3);
            return implode("", $firstFiveLines);
        } else {
            return "Error reading file.";
        }
    } else {
        return "File not found.";
    }
}

echo teaser("changelog.html");
?>
<br>
<a href="?p=changelog.php">Mehr öffnen</a>
