<?
header('Access-Control-Allow-Origin: *');
if( isset($_GET['tileset']) && preg_match('/^[\w\d_-]+$/', $_GET['tileset']) ) {
	$tileset = $_GET['tileset'];
	$flip = true;
	if( strlen($tileset) > 4 && substr($tileset,strlen($tileset)-4) == '-tms' ) {
		$tileset = substr($tileset,0,strlen($tileset)-4);
		$flip = false;
	}
	try {
		$db = new PDO('sqlite:'.$tileset.'.mbtiles','','',array(PDO::ATTR_PERSISTENT => true));
		if( !isset($db) ) {
			header('Content-type: text/plain');
			print 'Incorrect tileset name: '.$_GET['tileset'];
			exit;
		}
		// http://c.tile.openstreetmap.org/12/2392/1190.png
		$z = floatval($_GET['z']);
		$y = floatval($_GET['y']);
		$x = floatval($_GET['x']);
		if( $flip ) {
			$y = pow(2, $z) - 1 - $y;
		}
		$result = $db->query('select tile_data as t from tiles where zoom_level='.$z.' and tile_column='.$x.' and tile_row='.$y);
		$data = $result->fetchColumn();
		if( !isset($data) || $data === FALSE ) {
			$png = imagecreatetruecolor(256, 256);
			imagesavealpha($png, true);
			$trans_colour = imagecolorallocatealpha($png, 0, 0, 0, 127);
			imagefill($png, 0, 0, $trans_colour);
			header('Content-type: image/png');
			imagepng($png);
			//header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
		} else {
			$result = $db->query('select value from metadata where name="format"');
			$resultdata = $result->fetchColumn();
			$format = isset($resultdata) && $resultdata !== FALSE ? $resultdata : 'png';
			if( $format == 'jpg' )
				$format = 'jpeg';
			header('Content-type: image/'.$format);
			print $data;
		}
	} catch( PDOException $e ) {
		header('Content-type: text/plain');
		print 'Error querying the database: '.$e->getMessage();
	}
} elseif( isset($_GET['tmsinfo']) ) {
	// get tms xml
	header('Content-type: text/xml');
	print '<?xml version="1.0" encoding="UTF-8" ?>';
	$basetms = getbaseurl();
	if( $_GET['tmsinfo'] == 'root' ) {
		?><Services>
			<TileMapService title="MBTiles PHP TMS" version="1.0.0" href="http://<?=$basetms ?>1.0.0/" />
			</Services>
			<?
	} elseif( $_GET['tmsinfo'] == 'service' ) {
		?> <TileMapService version="1.0.0" services="<?=$basetms ?>">
			<Title>MBTiles PHP TMS</Title>
			<Abstract />
			<TileMaps>
<?	if( $handle = opendir('.') ) {
		while( ($file = readdir($handle)) !== false ) {
			if( preg_match('/^[\w\d_-]+\.mbtiles$/', $file) && is_file($file) ) {
				try {
					$db = new PDO('sqlite:'.$file);
					$params = readparams($db);
?>			<TileMap title="<?=htmlspecialchars($params['name']) ?>" srs="OSGEO:41001" profile="global-mercator" href="<?=$basetms.'1.0.0/'.str_replace('.mbtiles','',$file) ?>" />
<?				} catch( PDOException $e ) {}
			}
		}
	}
?>
			</TileMaps>
			</TileMapService>
			<?
	} elseif( $_GET['tmsinfo'] == 'resource' && isset($_GET['tmslayer'])  && preg_match('/^[\w\d_-]+$/', $_GET['tmslayer']) ) {
		$layer = $_GET['tmslayer'];
		try{
			$db = new PDO('sqlite:'.$layer.'.mbtiles');
			$params = readparams($db);
			?><TileMap version="1.0.0" tilemapservice="<?=$basetms ?>1.0.0/">
				<Title><?=htmlspecialchars($params['name']) ?></Title>
				<Abstract><?=htmlspecialchars($params['description']) ?></Abstract>
				<SRS>OSGEO:41001</SRS>
				<BoundingBox minx="-180" miny="-90" maxx="180" maxy="90" />
				<Origin x="0" y="0"/>
				<TileFormat width="256" height="256" mime-type="image/<?=$params['format'] == 'jpg' ? 'jpeg' : 'png' ?>" extension="<?=$params['format']?>"/>
				<TileSets profile="global-mercator">
<? foreach( readzooms($db) as $zoom ) { ?>
					<TileSet href="<?=$basetms.'1.0.0/'.$layer.'/'.$zoom ?>" units-per-pixel="<?=78271.516 / pow(2, $zoom) ?>" order="<?=$zoom ?>" />
<? } ?>
				</TileSets>
				</TileMap>
				<?
		} catch( PDOException $e ) {}
	}
} else {
	// show list of all tilesets along with links
	print '<h2>MBTiles PHP proxy</h2>';
	if( $handle = opendir('.') ) {
		$found = false;
		while( ($file = readdir($handle)) !== false ) {
			if( preg_match('/^[\w\d_-]+\.mbtiles$/', $file) && is_file($file) ) {
				try {
					$db = new PDO('sqlite:'.$file);
					$params = readparams($db);
					$zooms = readzooms($db);
					$db = null;
					print '<h3>'.htmlspecialchars($params['name']).'</h3>';
					if( isset($params['description']) )
						print '<p>'.htmlspecialchars($params['description']).'</p>';
					print '<p>Type: '.$params['type'].', format: '.$params['format'].', version: '.$params['version'].'</p>';
					if( isset($params['bounds']) )
						print '<p>Bounds: '.str_replace(',', ', ',$params['bounds']).'</p>';
					print '<p>Zoom levels: '.implode(', ', $zooms).'</p>';
					print '<p>OpenLayers: <tt>new OpenLayers.Layer.OSM("'.htmlspecialchars($params['name']).'", "'.getbaseurl().preg_replace('/\.mbtiles/','',$file).'/${z}/${x}/${y}", {numZoomLevels: '.(end($zooms)+1).', isBaseLayer: '.($params['type']=='baselayer'?'true':'false').'});</tt></p>';
					print '<p>TMS: <tt>http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/[^\/]+$/','/',$_SERVER['REQUEST_URI']).'1.0.0/'.preg_replace('/\.mbtiles/','',$file).'</tt></p>';
				} catch( PDOException $e ) {}
			}
		}
	} else {
		print 'Error opening script directory.';
	}
}

function getbaseurl() {
	return 'http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/(1.0.0\/)?[^\/]*$/','/',$_SERVER['REQUEST_URI']);
}

function readparams( $db ) {
	$params = array();
	$result = $db->query('select name, value from metadata');
	while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
		$params[$row['name']] = $row['value'];
	}
	return $params;
}

function readzooms( $db ) {
	$zooms = array();
	$result = $db->query('select zoom_level from tiles group by zoom_level order by zoom_level');
	while ($zoom = $result->fetchColumn()) {
		$zooms[] = $zoom;
	}
	return $zooms;
}
?>
