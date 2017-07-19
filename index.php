<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;
$app->post('/', function (Symfony\Component\HttpFoundation\Request $request) use ($app) {

  $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
  $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);

  $signature = $request->headers->get(\LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE);
  if (empty($signature)) {
      return new Symfony\Component\HttpFoundation\Response('Bad Request', 400);
  }
  try {
      $events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
  } catch (InvalidSignatureException $e) {
      return new Symfony\Component\HttpFoundation\Response('Invalid signature', 400);
  } catch (InvalidEventRequestException $e) {
      return new Symfony\Component\HttpFoundation\Response('Invalid event request', 400);
  }
  foreach ($events as $event) {
    if ($event instanceof \LINE\LINEBot\Event\MessageEvent) {
      if ($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage) {

        $actionArray = array();
        array_push($actionArray, new LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder(
            'Button Pushed!',
            // Button is 100px on 700px image. So 149px on base size(1040px)
            new LINE\LINEBot\ImagemapActionBuilder\AreaBuilder(0, 0, 149, 149)));

        $imagemapMessageBuilder = new \LINE\LINEBot\MessageBuilder\ImagemapMessageBuilder (
          'https://' . $_SERVER['HTTP_HOST'] . '/imagemap/' . uniqid(), // prevent cache
          "代替テキスト",
          new LINE\LINEBot\MessageBuilder\Imagemap\BaseSizeBuilder(1040, 1040),
          $actionArray
        );

        $response = $bot->replyMessage($event->getReplyToken(), $imagemapMessageBuilder);
        if(!$response->isSucceeded()) {
          error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
        }
      }
    }
  }
  return new Symfony\Component\HttpFoundation\Response('OK', 200);
});

$app->get('/imagemap/{uniqid}/{size}', function (Symfony\Component\HttpFoundation\Request $request, $uniqid, $size) use ($app) {
  $originalImage = imagecreatefrompng('imagemap.png');

  list($width, $height) = getimagesize("imagemap.png");
  $out = imagecreatetruecolor($size ,$size);
  imagecopyresampled($out, $originalImage, 0, 0, 0, 0, $size, $size, $width, $height);

  ob_start();
  imagepng($out, null, 9);
  $content = ob_get_contents();
  ob_end_clean();

  $response = new Symfony\Component\HttpFoundation\Response($content, 200);
  $response->headers->set('Content-Type', 'image/png');
  $response->headers->set('Content-Disposition', 'inline');
  return $response;
});

$app->run();


 ?>
