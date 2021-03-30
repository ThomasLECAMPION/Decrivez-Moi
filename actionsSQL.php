<?php
// Identifiants pour la base de données
$dsn = "mysql:host=mysql.info.unicaen.fr;port=3306;dbname=21806937_bd;charset=utf8";
$user = "21806937";
$pass = "groupe10";

// Connexion à la base MySQL
try {
$bd = new PDO($dsn, $user, $pass);
} catch (PDOException $e) { 
echo "Connexion échouée: " . $e->getMessage();
exit(0);
}

/* Description des deux tables utilisées:

___PHOTOS___  ______________TAGS______________
| id | url |  | name | photoid | originaltag |
------------  --------------------------------
*/

if($_POST['action'] == 'getPhotos') 
{
    $rq = 'SELECT * FROM photos';
    $stmt = $bd->prepare($rq);
    $stmt->execute();
    $photos = $stmt->fetchall();

    echo json_encode($photos);
} 
else if($_POST['action'] == 'getTags') 
{
    $rq = 'SELECT * FROM tags';
    $stmt = $bd->prepare($rq);
    $stmt->execute();
    $tags = $stmt->fetchall();

    echo json_encode($tags);
}
else if($_POST['action'] == 'updateTags') 
{
    $rq = 'INSERT INTO tags (name, photoid, originaltag) VALUES (:name, :photoid, false)';
    $stmt = $bd->prepare($rq);
    $data = array(':name' => $_POST['name'], ':photoid' => $_POST['photoid']);
    $stmt->execute($data);
}

// Permet d'inscrire de nouvelles images dans notre base de données
if(isset($_GET['tag'])) {

    $params=['method'=>'flickr.photos.search', 
    'api_key'=>'10c8842b048394759adc11a0ac87e393', 
    'tags'=>$_GET['tag'],
    'per_page'=>'50',
    'sort'=>'relevance',
    'format'=>'json', 
    'nojsoncallback'=>'1'];
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://www.flickr.com/services/rest/');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $output = json_decode(curl_exec($ch));
    $photos = $output->photos->photo;

    foreach ($photos as $photo) {
        $params=['method'=>'flickr.photos.getInfo', 
        'api_key'=>'10c8842b048394759adc11a0ac87e393', 
        'photo_id'=>$photo->id,
        'format'=>'json', 
        'nojsoncallback'=>'1'];

        curl_setopt($ch, CURLOPT_URL, 'https://www.flickr.com/services/rest/');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $output = json_decode(curl_exec($ch));
        $photo = $output->photo;
        $url = 'https://farm'.$photo->farm.'.staticflickr.com/'.$photo->server.'/'.$photo->id.'_'.$photo->secret.'.jpg';

        if(count($photo->tags->tag)>=5) {
            $rq = 'INSERT INTO photos (url) VALUES (:url)';
            $stmt = $bd->prepare($rq);
            $data = array(':url' => $url);
            $stmt->execute($data);
            
            $rq = 'SELECT id FROM photos WHERE url=:url';
            $stmt = $bd->prepare($rq);
            $data = array(':url' => $url);
            $stmt->execute($data);
            $photo_id = $stmt->fetch()['id'];
            
            foreach ($photo->tags->tag as $tag) {
                $rq = 'INSERT INTO tags (name, photoid) VALUES (:name, :photoid)';
                $stmt = $bd->prepare($rq);
                $data = array(':name' => $tag->_content, ':photoid' => $photo_id);
                $stmt->execute($data);
            }
        }
    }

    curl_close($ch);
}