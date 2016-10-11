# Flussonic module for IPTV Matrix

### 1. Prerequisities
  To install and run module you need only two things: 
  1. git `git`
  2. PHP with curl extenstion `php5` `php5-curl`

You can install them with apt-get

### 2. Installation
Next, you need to clone this repository and run `install.sh` script, be sure, that user have write access to /usr/local

1. Go to your home directory `cd ~`
2. Clone this repository `git clone https://github.com/iptvmatrix/flussonic_module.git`
3. Run install script `cd flussonic_module && sudo ./install.sh`
4. On success install add cron task (install script will give you line for cron)
5. Wait for 5 minutes, and module should start work

### 3. Configuration

Configuration file located in `/usr/local/net.iptvmatrix/flussonic_module/config/matrix.cfg`
