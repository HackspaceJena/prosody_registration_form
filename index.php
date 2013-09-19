<?php
require 'vendor/autoload.php';

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

$app->run();
