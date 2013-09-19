<?php
require 'vendor/autoload.php';
require_once 'is_email.php';

use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();

$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/templates',
));

$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallback' => 'de',
));

$app['translator'] = $app->share($app->extend('translator', function($translator, $app) {
    $translator->addResource('xliff', __DIR__.'/locales/de.xml', 'de');
    $translator->addResource('xliff', __DIR__.'/locales/en.xml', 'en');

    return $translator;
}));

$app->before(function(Request $request) use ($app){
  $lang = $request->getPreferredLanguage(array('en', 'de'));
  $app['translator']->setLocale($lang);
});

$app->get('/', function (Request $request) use ($app) {
  return $app['twig']->render('registration_form.twig', array(
      'errors' => array(),
  ));
});

$app->post('/', function (Request $request) use ($app) {
  $errors = array();

  // collect the params
  $user = $request->get('username',null);
  $host = $request->get('host',null);
  $email = $request->get('mail',null);
  $password = $request->get('password',null);
  $password_repeat = $request->get('password_repeat',null);

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
  
  if (count($errors) > 0) {
    return $app['twig']->render('registration_form.twig', array(
      'errors' => $errors,
    ));
  } else {
    return $app['twig']->render('success.twig', array(
    ));    
  }
});

$app->run();
