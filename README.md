# Flussonic module for IPTV Matrix

### 1. Prerequisities
  Flussonic iptvmatrix module is running under Linux and require the following software:
  1. git
  2. PHP 5 with curl
  3. Flussonic
  4. Cron service
  
  You should already have cron service in your Linux installation. Flussonic installation guide is available here:
  http://flussonic.com/doc/installation
  
  To install the rest, run:
  `apt-get update`
  `apt-get install git php5 php5-curl`
  
  PHP will also install Apache. It is not required for the module, it is highly recommended to immediately remove it with
  `apt-get purge apache2`

  You will also need server added in iptvmatrix.net in Equipment --> Servers, Flussonic added in Equipment --> Media servers, and at least one application added in Equipment --> Apps
  
### 2. Installation

1. Go to your home directory `cd ~`
2. Clone this repository `git clone https://github.com/iptvmatrix/flussonic_module.git`
3. Edit configuration file `vi flussonic_module/config/matrix.cfg`
     
	 change flussonic_host to point to flussonic http host and port, usually it is
	 `flussonic_host=http://localhost:80`
	 
	 change flussonic_user and flussonic_pwd to ones set in /etc/flussonic/flussonic.conf
	 
	 change flussonic_id and set it to Equipment --> Media Servers --> ID of Flussonic.
	 
	 add applications in [apps] section, in format
	 `name=type`
	 where name is name of Application added in Equipment --> Apps, and types are:
	 1 - origin / 2 - edge / 3 - dvr origin / 4 - dvr edge
	 
4. Run installation script `cd flussonic_module && sudo ./install.sh`
5. Script will print the settings for the crontab. Copy and paste it into cron with `crontab -e`
6. Start Flussonic

### 3. Changing configuration after installation

Configuration file of installed module is located in `/usr/local/net.iptvmatrix/flussonic_module/config/matrix.cfg`

### 4. Updating

1. Remove previus version from your home directory `cd ~ && rm -rf flussonic_module`
2. Clone this repository `git clone https://github.com/iptvmatrix/flussonic_module.git`
3. Run update.sh, it will copy new version in default installation folder (your configuration file will be saved)
