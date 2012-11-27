<?php
// Einbinden des Autoloaders
require_once __DIR__.'/vendor/autoload.php';

// Silex initialisieren
$app = new Silex\Application();
$app['debug'] = true;

// Couchbase initialisieren
$cb = new Couchbase("127.0.0.1", "beer-sample", "", "beer-sample");

// Template Engine registrieren
$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__.'/templates'
));

// Die ersten 15 Biere/Brauereien
$app->get('/', function() use ($app, $cb) {
	$options = array('limit' => '15', 'stale' => 'false');
	$results = $cb->view("beer", "brewery_beers", $options);

	$docs = array();
	foreach($results['rows'] as $row) {
		$id = $row['id'];
		$doc = json_decode($cb->get($row['id']), true);
		$docs[] = $doc + array('id' => $row['id']);
	}

	return $app['twig']->render('index.twig.html', array (
		'docs' => $docs
	));
});

// Detailansicht eines Datensatzes
$app->get('/show/{id}', function($id) use ($app, $cb) {
	$doc = $cb->get($id);
	if(!$doc) {
		return $app->abort(404, 'Document not found');
	}
	
	return $app['twig']->render('show.twig.html', array (
		'doc' => json_decode($doc, true)
	));	
});

// Neues Bier oder Brauerei erzeugen
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
$app->post('/add/{type}', function(Request $request, $type) use ($app, $cb) {
	if($request->headers->get('Content-Type') != 'application/json') {
		return $app->abort(500, 'Nur JSON erlaubt!');
	}
	
	$data = json_decode($request->getContent(), true);
	if(empty($data) || empty($data['name'])) {
		return $app->abort(500, 'Keine Daten zum speichern erhalten!');
	}

	$key = $type . '_' . str_replace(" ", "_", strtolower($data['name']));
	$data = json_encode($data + array('type' => $type));

	$result = $cb->set($key, $data);
	if($result == false) {
		return $app->abort(500, 'Fehler beim Speichern!');
	}
	
	return new Response("Dokument unter $key gespeichert!", 201);
});

// Anfrage abarbeiten
$app->run();
?>