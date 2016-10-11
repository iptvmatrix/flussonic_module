<?php
include 'bootstrap.php';
use App as App;

// Load config with sections
$config = parse_ini_file('config/matrix.cfg', 1);

$ma  = new App\MatrixApi($config);
$fa  = new App\FlussonicApi($config);

//if config dont contain apps
if (empty($config['apps'])) {
    die();
}

//Get all streams and connections from flussonic
$fa->getStreams();
$fa->getSessions();

foreach ($config['apps'] as $app_name => $type)
{
    $app = new App\Core($ma, $fa, $config);
    $app->setApp($app_name, $type);

    if ($app->isFirstRun()) {
        $app->createStateFile();
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
    $feeds   = $fa->getFeeds($app_name);
    $clients = $fa->getClients($app_name);

    // Build report
    $report->setDevicesMinLifetime($app->getConfigArg(App\MatrixApi::DEVICE_MIN_LIFETIME))
        ->setFeedsStatus($feeds)
        ->setConnectedClients($clients)
        ->buildReport($withDevices = $app->isDeviceReportNeeded());

    if ($withDevices) {
        $app->setConfigArg(App\Core::LAST_DEVICES_REPORT, time());
    }

    // Drop duplicated connections
    if ($duplicated_connections = $report->getDuplicatedConnections()) {
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
                break;

            case App\Core::TYPE_EDGE:
                $url = $feed;
                $fa->createFeed($stream_name, $url, $persistent = false);
                break;
        }
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
        $token = $fa->hashToToken($hash, $app_name);
        $fa->removeDevices([$token]);
    }

    //update config from Matrix Answer
    if ($ma->getAnswerArg(App\MatrixApi::HEARTBEAT_URL)) {
        $app->setConfigArg(App\MatrixApi::HEARTBEAT_URL);
    }
    if ($ma->getAnswerArg(App\MatrixApi::DEVICE_MIN_LIFETIME)) {
        $app->setConfigArg(App\MatrixApi::DEVICE_MIN_LIFETIME);
    }

    //update last run in state file
    $app->updateStateFileLastRun()
        ->saveMatrixCfg();

    $report->updateFeedsStatusesFile();
}
