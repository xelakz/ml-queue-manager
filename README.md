# multiline-queue

## 1. Install

#### 1.1 server via composer 
```
git clone https://{username}@bitbucket.org/nptcore/ml-qm.git
cd ml-qm

```


```
composer install
```
#### 1.2 docker
* git clone https://{username}@bitbucket.org/nptcore/ml-qm.git
* cd ml-qm
* Building a mirror based on the root directory Dockerfile
* docker build -t ninepinetech/ml-qm .
* docker run  -t ml-qm bash
* After entering the docker container, enter the project directory:
  * `php ./bin/ml-qm.php start`

## 2. How to run

#### 2.1 Run in redis machine to create list of proxies 

```
redis-cli sadd url-proxy-ips "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.120.194:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.122.92:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.123.14:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.29.167:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.31.42:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.31.228:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.120.167:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.25.30:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.26.178:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.26.206:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.120.194:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.122.92:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.123.14:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.29.167:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.31.42:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.31.228:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.120.167:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.25.30:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.26.178:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.26.206:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.122.122:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.29.21:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.121.213:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.28.25:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.27.159:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.122.178:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.26.58:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.120.16:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.27.86:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.28.120:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.25.191:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.123.182:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.29.101:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.120.131:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.27.27:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.31.113:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.24.247:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.24.252:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.123.107:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.24.206:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.31.232:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.30.120:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.28.89:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.122.176:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.30.116:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.30.147:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.29.102:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.121.16:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.121.215:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.25.99:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.121.232:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.28.217:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.30.166:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.27.211:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.27.251:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.26.84:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.25.165:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.123.127:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.24.51:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
    "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.27.150:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225"
```

#### 2.2 example 

```
1.start service
php ./bin/ml-qm.php worker:start -d >> log/system.log 2>&1
or
php ./bin/ml-qm.php worker:start

2.push jobs
php test/Job/login.php
php test/Job/leagues.php
```

