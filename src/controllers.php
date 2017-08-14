<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//Request::setTrustedProxies(array('127.0.0.1'));

$app->get('/', function () use ($app) {
    return $app->redirect('gallery');
});

$app->get('/about', function () use ($app) {
    return $app['twig']->render('about.html.twig', array());
})
    ->bind('about');

$app->get('/addFlickr', function () use ($app) {
    return $app['twig']->render('addFlickr.html.twig', array());
})
    ->bind('add_flickr');

$app->get('/uploadFlickr', function() use ($app) {
    $url = $_GET['url'];
    $filename = basename($url);

    $proxy = 'http://proxy.unicaen.fr:3128';

    $curl = curl_init();
    //curl_setopt($curl, CURLOPT_PROXY, $proxy); // A activer sur serveur unicaen
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);

    // Récupération du résultat
    $res = curl_exec($curl);
    file_put_contents('../web/images/'.$filename, $res);
    exec('exiftool -g -json images/' . $filename . ' > meta/'.$filename.'.json');

    return $app->redirect('gallery');
})
    ->bind('uploadFlickr');

$app->get('/gallery', function () use ($app) {
    $files = scandir('../web/images/');
    $images = array();
    foreach ($files as $file) {
        if(!strpos($file, 'original'))
            $images[]= $file;
    }
    return $app['twig']->render('gallery.html.twig', array( 'files' => $images));
})
    ->bind('gallery');


$app->get('/image/{name}', function (Silex\Application $app, $name) use ($app) {
    $metas = json_decode(file_get_contents("../web/meta/" . $name . ".json"), true);
    return $app['twig']->render('imageEdit.html.twig', array( 'fileName' => $name, 'metas' => $metas[0]));
})
    ->bind('image');

$app->get('/imageDetail/{name}', function (Silex\Application $app, $name) use ($app) {
    $metas = json_decode(file_get_contents("../web/meta/" . $name . ".json"), true);
    return $app['twig']->render('imageDetail.html.twig', array( 'fileName' => $name, 'metas' => $metas[0]));
})
    ->bind('image_detail');

$app->get('/meta', function (Request $request) use ($app){
    $filename = $_GET['filename'];
    $meta = file_get_contents('meta/'.$filename.'.json');
    return $app->json($meta);
})
    ->bind('meta');

$app->get('/addImage', function () use ($app) {
    return $app['twig']->render('addImage.html.twig', array());
})
    ->bind('add_image');

$app->post('/addImage', function () use ($app) {
    $errors = array();
    $maxSize = 1048576;
    // Validation
    if ($_FILES['image']['error'] > 0) $errors['image'][] = "Error while transfering file";
    if ($_FILES['image']['size'] > $maxSize) $errors['image'][] = "File size exceed " . $maxSize;

    $valid_extensions = array('jpg', 'jpeg', 'gif', 'png');
    $extension_upload = strtolower(substr(strrchr($_FILES['image']['name'], '.'), 1));
    if (!in_array($extension_upload, $valid_extensions))  $errors['image'][] = "Incorrect extension (jpg, jpeg, gif, png allowed)";

    if (count($errors) > 0) {
        return $app['twig']->render('addImage.html.twig', array(
            "errors" => $errors
        ));
    } else {


        //$new_name = md5(uniqid(rand(), true)).'.'.$extension_upload;

        $imageName = "";
        $path = $_FILES['image']['name'];

        if(isset($_POST["imageName"]))
        {

          $ext = pathinfo($path, PATHINFO_EXTENSION);
          $imageName = $_POST["imageName"] . "." . $ext;
        }

        else
        {
          $imageName = $_FILES['image']['name'];
        }

        $res = move_uploaded_file($_FILES['image']['tmp_name'] ,"images/".$imageName);
        if ($res) echo "Transfert réussi";
        exec('exiftool -g -json images/' . $imageName . ' > meta/'.$imageName.'.json');
        return $app->redirect('gallery');
    }


})
    ->bind('upload_image');

$app->post('/modifyImage', function () use ($app) {
    $query = "";
    foreach ($_POST as $key => $value) {
        if($key !="FileName")
            $query.="\"-$key=$value\" ";
    }

    $filename = $_POST['OriginalFileName'];

    exec('exiftool '.$query.'images/' . $filename . ' 1> logs/'.$filename.date("Y-m-d_H-i-s").'.txt 2>&1');
    exec('exiftool -g -json images/' . $filename . ' > meta/'.$filename . '.json');
    return $app->redirect('gallery');
})
    ->bind('modify_image');


$app->get('/removeImage/{name}', function (Silex\Application $app, $name)
{
      unlink('images/' . $name);
      unlink('meta/' . $name . ".json");
      unlink('images/' . $name . "_original");

      return $app->redirect('../gallery');
})
    ->bind('remove_image');

$app->error(function (\Exception $e, Request $request, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/' . $code . '.html.twig',
        'errors/' . substr($code, 0, 2) . 'x.html.twig',
        'errors/' . substr($code, 0, 1) . 'xx.html.twig',
        'errors/default.html.twig',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});
