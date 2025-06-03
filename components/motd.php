<!DOCTYPE html>
<?php
function GetExtUrl($url) {
        $ch = curl_init();
    
        // cURL Optionen setzen
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Rückgabe als String
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);           // Timeout setzen
    
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
    
        if ($antwort === false) {
            return "Fehler: $error";
        }
    
        return $result;
    }
?>
<html lang="en">
<head>
  <meta charset="UTF-8">
</head>
<body>

  <b><center><pre class="rt-text">

                                                                                                                                                                                                         dddddddd                    
                      iiii  lllllll                                                                        tttt                                                  hhhhhhh                                 d::::::d                    
                     i::::i l:::::l                                                                     ttt:::t                                                  h:::::h                                 d::::::d                    
                      iiii  l:::::l                                                                     t:::::t                                                  h:::::h                                 d::::::d                    
                            l:::::l                                                                     t:::::t                                                  h:::::h                                 d:::::d                     
rrrrr   rrrrrrrrr   iiiiiii  l::::l     eeeeeeeeeeee    yyyyyyy           yyyyyyy                 ttttttt:::::ttttttt        eeeeeeeeeeee        cccccccccccccccc h::::h hhhhh                   ddddddddd:::::d     eeeeeeeeeeee    
r::::rrr:::::::::r  i:::::i  l::::l   ee::::::::::::ee   y:::::y         y:::::y                  t:::::::::::::::::t      ee::::::::::::ee    cc:::::::::::::::c h::::hh:::::hhh              dd::::::::::::::d   ee::::::::::::ee  
r:::::::::::::::::r  i::::i  l::::l  e::::::eeeee:::::ee  y:::::y       y:::::y                   t:::::::::::::::::t     e::::::eeeee:::::ee c:::::::::::::::::c h::::::::::::::hh           d::::::::::::::::d  e::::::eeeee:::::ee
rr::::::rrrrr::::::r i::::i  l::::l e::::::e     e:::::e   y:::::y     y:::::y    --------------- tttttt:::::::tttttt    e::::::e     e:::::ec:::::::cccccc:::::c h:::::::hhh::::::h         d:::::::ddddd:::::d e::::::e     e:::::e
 r:::::r     r:::::r i::::i  l::::l e:::::::eeeee::::::e    y:::::y   y:::::y     -:::::::::::::-       t:::::t          e:::::::eeeee::::::ec::::::c     ccccccc h::::::h   h::::::h        d::::::d    d:::::d e:::::::eeeee::::::e
 r:::::r     rrrrrrr i::::i  l::::l e:::::::::::::::::e      y:::::y y:::::y      ---------------       t:::::t          e:::::::::::::::::e c:::::c              h:::::h     h:::::h        d:::::d     d:::::d e:::::::::::::::::e 
 r:::::r             i::::i  l::::l e::::::eeeeeeeeeee        y:::::y:::::y                             t:::::t          e::::::eeeeeeeeeee  c:::::c              h:::::h     h:::::h        d:::::d     d:::::d e::::::eeeeeeeeeee  
 r:::::r             i::::i  l::::l e:::::::e                  y:::::::::y                              t:::::t    tttttte:::::::e           c::::::c     ccccccc h:::::h     h:::::h        d:::::d     d:::::d e:::::::e           
 r:::::r            i::::::il::::::le::::::::e                  y:::::::y                               t::::::tttt:::::te::::::::e          c:::::::cccccc:::::c h:::::h     h:::::h        d::::::ddddd::::::dde::::::::e          
 r:::::r            i::::::il::::::l e::::::::eeeeeeee           y:::::y                                tt::::::::::::::t e::::::::eeeeeeee   c:::::::::::::::::c h:::::h     h:::::h ......  d:::::::::::::::::d e::::::::eeeeeeee  
 r:::::r            i::::::il::::::l  ee:::::::::::::e          y:::::y                                   tt:::::::::::tt  ee:::::::::::::e    cc:::::::::::::::c h:::::h     h:::::h .::::.   d:::::::::ddd::::d  ee:::::::::::::e  
 rrrrrrr            iiiiiiiillllllll    eeeeeeeeeeeeee         y:::::y                                      ttttttttttt      eeeeeeeeeeeeee      cccccccccccccccc hhhhhhh     hhhhhhh ......    ddddddddd   ddddd    eeeeeeeeeeeeee  
                                                              y:::::y                                                                                                                                                                
                                                             y:::::y                                                                                                                                                                 
                                                            y:::::y                                                                                                                                                                  
                                                           y:::::y                                                                                                                                                                   
                                                          yyyyyyy                                                                                                                                                                  

</pre></center></b>
    
    <?php 
        $quote =htmlspecialchars(GetExtUrl("https://riley-tech.de/w/api/site/return-161/"));
        echo "<p style='color:var(--color-text-rt);text-align: center;'> $quote </p>";
    ?>
</body>
</html>
