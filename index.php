<?php
require 'vendor/autoload.php';
require_once 'is_email.php';
require_once 'config.php';

use Silex\Application\TranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Guzzle\Http\Client;

use Silex\Application;

class RegistrationApplication extends Application {
  use Application\TranslationTrait;
  use Application\TwigTrait;
}

$app = new RegistrationApplication();

$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path' => __DIR__ . '/templates',
));

$app->register(new Silex\Provider\TranslationServiceProvider(), array(
  'locale_fallback' => 'de',
));

$app['translator'] = $app->share($app->extend('translator', function ($translator, $app) {
  $translator->addResource('xliff', __DIR__ . '/locales/de.xml', 'de');
  $translator->addResource('xliff', __DIR__ . '/locales/en.xml', 'en');

  return $translator;
}));

$app->before(function (Request $request) use ($app) {
  $lang = $request->getPreferredLanguage(array('en', 'de'));
  $app['translator']->setLocale($lang);
});

$app->get('/', function (Request $request) use ($app, $config) {
  return $app['twig']->render('registration_form.twig', array(
    'hosts' => $config['hosts'],
    'errors' => array(),
  ));
});

$app->post('/', function (Request $request) use ($app, $config) {
  $errors = array();

  // collect the params
  $user = $request->get('username', null);
  $host = $request->get('host', null);
  $email = $request->get('mail', null);
  $password = $request->get('password', null);
  $password_repeat = $request->get('password_repeat', null);

  // check for errors
  if (!$user) {
    $errors[] = $app->trans('Kein Benutzername angegeben.');
  }

  if (!$host) {
    $errors[] = $app->trans('Keinen Hostnamen angegeben.');
  }

  if (!$email) {
    $errors[] = $app->trans('Keine E-Mail-Adresse angegeben.');
  } else {
    if (!is_email($email)) {
      $errors[] = $app->trans('Keine gültige E-Mail-Adresse angegeben.');
    }
  }

  if (!$password) {
    $errors[] = $app->trans('Kein Passwort angegeben.');
  }

  if ($password != $password_repeat) {
    $errors[] = $app->trans('Bitte gebe in den Feldern Passwort und Passwortwiederholung identische Werte ein.');
  }

  if (count($errors) == 0) {
    $client = new Client($config['prosody']['http_base']);

    $request = $client
      ->get($config['prosody']['url_prefix'] . 'user/' . $user, array('Host' => $host))
      ->setAuth($config['prosody']['user'], $config['prosody']['password']);

    try {
      $response = $request->send();
      if ($response->getCode() != 404) {
        $errors[] = $app->trans('Der Benutzername ist bereits vergeben.');
      }
    } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
        
    }
  }

  if (count($errors) == 0) {

    $client = new Client($config['prosody']['http_base']);

    $data = json_encode(array(
      'username' => $user,
      'password' => $password,
      'server' => $host,
      'mail' => $email,
    ));

    $token = sha1($data);

    if (strlen($token) > 0) {
      file_put_contents('validations/' . $token, $data);
      $message = Swift_Message::newInstance()
        ->setSubject($app->trans('Registrierung auf %server%', array('%server%' => $host)))
        ->setFrom($config['from'])
        ->setTo($email)
        ->setBody($app['twig']->render(sprintf('email.%s.twig', $app['translator']->getLocale()), array('auth_token' => $token, 'url' => $config['url'])));

      $transport = Swift_SendmailTransport::newInstance('/usr/sbin/sendmail -bs');

      $mailer = Swift_Mailer::newInstance($transport);

      $result = $mailer->send($message);

      if (!$result) {
        $errors[] = $app->trans('Beim Mailversand ist ein Fehler aufgetreten.');
      }
    }
  }

  if (count($errors) > 0) {
    return $app['twig']->render('registration_form.twig', array(
      'hosts' => $config['hosts'],
      'errors' => $errors,
    ));
  } else {
    return $app['twig']->render('success.twig', array());
  }
});

$app->get('/{verifycode}', function ($verifycode) use ($app, $config) {
  if (file_exists('validations/' . $verifycode)) {
    $data = json_decode(file_get_contents('validations/' . $verifycode));

    $jid = $data->username . '@' . $data->server;

    $client = new Client($config['prosody']['http_base']);

    $request = $client
      ->post($config['prosody']['url_prefix'] . 'user/' . $data->username, array(
        'Host' => $data->server,
      ), json_encode(array('password' => $data->password)))
      ->setAuth($config['prosody']['user'], $config['prosody']['password']);

    $response = $request->send();

    if ($response->getStatusCode() == 201) {
      return $app->render('welcome.twig', array('jid' => $jid));
    } else {
      return $app->render('error.twig', array('url' => $config['url']));
    }

  } else {
    return $app->render('tokennotfound.twig');
  }
});

$app->run();
