<?php
include 'bootstrap.php';
use App as App;

// Load config with sections
$config = parse_ini_file('config/matrix.cfg', 1);

$ma      = new App\MatrixApi($config);
$fa      = new App\FlussonicApi($config);
$sysStat = new App\SystemStatus();

$sysStat->obtainIFaces()->initMeasurement();

$server_cpu = $sysStat->getMemoryUsage();
$server_mem = $sysStat->getCpuUsage();

//if config dont contain apps
if (empty($config['apps'])) {
    die("No Apps found!");
}

//Get all streams and connections from flussonic
$fa->getStreams();
$fa->getSessions();
$fa->getSources();

$server_cpu = $sysStat->getMemoryUsage();
$server_mem = $sysStat->getCpuUsage();

//index for dvr edge applications. Need for flussonic API
$dvr_edge_apps = 0;

foreach ($config['apps'] as $app_name => $type)
{
    $app = new App\Core($ma, $fa, $config);
    $app->setApp($app_name, $type);

    if ($app->isFirstRun()) {
        $app->createStateFile()
            ->updateAuthUrl();
        $fa->removeAllStreams($app_name);
        echo("$app_name First Run" . PHP_EOL);
        continue;
    }

    if (!$app->isRunNeeded()) {
        echo "$app_name: wait!".PHP_EOL;
        continue;
    }

    $report = new App\ApplicationReport($app_name, $type);

    // Get streams and connected clients for current app
    switch ($type) {
        case App\Core::TYPE_DVR_EDGE:
            $only    = $fa->getDvrEdgeReplicatedStreams($app_name);
            $clients = $fa->getDvrEdgeSessions($only);
            $feeds   = [];
            break;

        default:
            $feeds   = $fa->getFeeds($app_name);
            $clients = $fa->getClients($app_name);
            break;
    }

    // Build report
    $server_network_status = $sysStat->getNetworkUsage();

    $report->setDevicesMinLifetime($app->getConfigArg(App\MatrixApi::DEVICE_MIN_LIFETIME))
        ->setSystemStatus($server_network_status, $server_cpu, $server_mem)
        ->setFeedsStatus($feeds)
        ->setConnectedClients($clients)
        ->buildReport($withDevices = $app->isDeviceReportNeeded());

    if ($withDevices) {
        $app->setConfigArg(App\Core::LAST_DEVICES_REPORT, time());
    }

    // Drop duplicated connections
    if ($duplicated_connections = $report->getDuplicatedConnections()) {
        echo ("DROPPING: "); pr($duplicated_connections);
        $fa->removeDevices($duplicated_connections);
    }

    $to = $app->getConfigArg(App\MatrixApi::HEARTBEAT_URL);

    $ma->clearAnswers()
        ->sendReport($app_name, $type, $to, $report);

    // add new feeds, or update existing
    foreach ($ma->getFeedsUpdates() as $id => $feed)
    {
        $stream_name = $app->generateStreamName($id);
        switch ($type) {
            case App\Core::TYPE_ORIGIN:
                $url = $feed['url'];
                if ($feed['method'] == 1) {
                    $fa->createFeed($stream_name, $url, $persistent = true);
                }
                if ($feed['method'] == 2) {
                    $fa->createPushEndpoint($stream_name, $pwd='mock');
                }
                break;

            case App\Core::TYPE_EDGE:
                $url = $feed;
                $fa->createFeed($stream_name, $url, $persistent = false);
                break;
        }
    }


    //DVR ORIGIN CHANNELS
    $valid_state = [];
    foreach ($ma->getRecords() as $channel_id => $data)
    {
        $stream_name = $app->generateStreamName($channel_id);
        $depth = $data['depth'] * 60 * 60 * 24; //Days to secs
        $fa->updateDvrChannel($stream_name, $data['streams'], $data['folder'], $depth);

        $valid_state[] = $stream_name;
    }

    //GET REMOVED DVR CHANNELS
    if ($type == App\Core::TYPE_DVR_ORIGIN) {
        $current_state = $fa->getFeeds($app_name, $only_names = true);

        foreach (array_diff($current_state, $valid_state) as $stream) {
            $fa->deleteStream($stream);
        }
    }

    //Set cluster key if have
    if ($key = $ma->getAnswerArg(App\MatrixApi::CLUSTER_KEY)) {
        $fa->setServerClusterKey($key);
    }

    //DVR EDGE
    foreach ($ma->getSources() as $source)
    {
        $fa->updateDvrEdge(++$dvr_edge_apps, $app_name, $source);
    }

    // remove deleted feeds
    foreach ($ma->getFeedsRemove() as $id) {
        $stream_name = $app->generateStreamName($id);
        $fa->deleteStream($stream_name);
    }


    foreach ($ma->getFeedsReset() as $id) {
        $fa->restartStream($app->generateStreamName($id));
    }

    foreach ($ma->getKillDevices() as $hash) {
        $token = $fa->hashToToken($hash, $app_name, $type);
        $fa->removeDevices([$token]);
    }


    //update config from Matrix Answer
    if ($value = $ma->getAnswerArg(App\MatrixApi::HEARTBEAT_URL)) {
        $app->setConfigArg(App\MatrixApi::HEARTBEAT_URL, $value);
    }

    if ($value = $ma->getAnswerArg(App\MatrixApi::DEVICE_MIN_LIFETIME)) {
        $app->setConfigArg(App\MatrixApi::DEVICE_MIN_LIFETIME, $value);
    }

    if ($url = $ma->getAnswerArg(App\MatrixApi::STREAM_AUTH_URL)) {
        $this->fa->setGlobalAuth($url);
    }

    //update last run in state file
    $app->updateStateFileLastRun()
        ->saveMatrixCfg();

    $report->updateFeedsStatusesFile();
}

$streams = [];
$sources = [];

foreach ($config['apps'] as $app => $type) {
    if ($type == App\Core::TYPE_DVR_EDGE) {
        $sources[] = $app;
    } else {
        $streams[] = $app;
    }
}

$flussonic_cfg = $fa->readConfig();

$flussonic_sources = $fa->getAllSourcesFromConfig($flussonic_cfg);
$flussonic_streams = $fa->getAllStreamsFromConfig($flussonic_cfg);

foreach ($flussonic_streams as $stream_name => $data)
{
    if (!in_array(split('/', $stream_name)[0], $streams)) {
        $fa->deleteStream($stream_name);
    }
}

foreach ($flussonic_sources as $source_id => $data) {
    if (empty($data['meta']['comment']) ||
        !in_array($data['meta']['comment'], $sources)
    ) {
        $fa->deleteSource($source_id);
    }
}

