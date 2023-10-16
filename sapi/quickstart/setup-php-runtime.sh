#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
if [ -f ${__DIR__}/prepare.php ] ; then
  __PROJECT__=$(
    cd ${__DIR__}/
    pwd
  )
else
  __PROJECT__=$(
    cd ${__DIR__}/../../
    pwd
  )
fi

cd ${__PROJECT__}

OS=$(uname -s)
ARCH=$(uname -m)

case $OS in
'Linux')
  OS="linux"
  ;;
'Darwin')
  OS="macos"
  ;;
*)
  echo '暂未配置的 OS '
  exit 0
  ;;

esac

case $ARCH in
'x86_64')
  ARCH="x64"
  ;;
'aarch64')
  ARCH="arm64"
  ;;
*)
  echo '暂未配置的 ARCH '
  exit 0
  ;;
esac


VERSION='v5.0.3'

mkdir -p bin/runtime
mkdir -p var/runtime

cd ${__PROJECT__}/var/runtime

SWOOLE_CLI_DOWNLOAD_URL="https://github.com/swoole/swoole-src/releases/download/${VERSION}/swoole-cli-${VERSION}-${OS}-${ARCH}.tar.xz"
COMPOSER_DOWNLOAD_URL="https://getcomposer.org/download/latest-stable/composer.phar"
CACERT_DOWNLOAD_URL="https://curl.se/ca/cacert.pem"

MIRROR=''
while [ $# -gt 0 ]; do
  case "$1" in
  --mirror)
    MIRROR="$2"
    ;;
  --proxy)
    export HTTP_PROXY="$2"
    export HTTPS_PROXY="$2"
    NO_PROXY="127.0.0.1,localhost,127.0.0.0/8,10.0.0.0/8,100.64.0.0/10,172.16.0.0/12,192.168.0.0/16,198.18.0.0/15,169.254.0.0/16"
    NO_PROXY="127.0.0.0/8,10.0.0.0/8,100.64.0.0/10,172.16.0.0/12,192.168.0.0/16,198.18.0.0/15,169.254.0.0/16"
    export NO_PROXY="${NO_PROXY},127.0.0.1,localhost"
    ;;
  --*)
    echo "Illegal option $1"
    ;;
  esac
  shift $(($# > 0 ? 1 : 0))
done

case "$MIRROR" in
china)
  SWOOLE_CLI_DOWNLOAD_URL="https://wenda-1252906962.file.myqcloud.com/dist/swoole-cli-${VERSION}-${OS}-${ARCH}.tar.xz"
  COMPOSER_DOWNLOAD_URL="https://mirrors.aliyun.com/composer/composer.phar"
  ;;

esac

test -f composer.phar || wget -O composer.phar ${COMPOSER_DOWNLOAD_URL}
chmod a+x composer.phar

test -f cacert.pem || wget -O cacert.pem ${CACERT_DOWNLOAD_URL}

SWOOLE_CLI_RUNTIME="swoole-cli-${VERSION}-${OS}-${ARCH}"

test -f ${SWOOLE_CLI_RUNTIME}.tar.xz || wget -O ${SWOOLE_CLI_RUNTIME}.tar.xz ${SWOOLE_CLI_DOWNLOAD_URL}
test -f ${SWOOLE_CLI_RUNTIME}.tar || xz -d -k ${SWOOLE_CLI_RUNTIME}.tar.xz
test -f swoole-cli || tar -xvf ${SWOOLE_CLI_RUNTIME}.tar
chmod a+x swoole-cli
cp -f ${__PROJECT__}/var/runtime/swoole-cli ${__PROJECT__}/bin/runtime/php

cd ${__PROJECT__}/var/runtime

cp -f ${__PROJECT__}/var/runtime/composer.phar ${__PROJECT__}/bin/runtime/composer
cp -f ${__PROJECT__}/var/runtime/cacert.pem ${__PROJECT__}/bin/runtime/cacert.pem

cat >${__PROJECT__}/bin/runtime/php.ini <<EOF
curl.cainfo="${__PROJECT__}/bin/runtime/cacert.pem"
openssl.cafile="${__PROJECT__}/bin/runtime/cacert.pem"
swoole.use_shortname=off

EOF

cd ${__PROJECT__}/

set +x

echo " "
echo " USE PHP RUNTIME :"
echo " "
echo " export PATH=\"${__PROJECT__}/bin/runtime:\$PATH\" "
echo " "
echo " alias php='php -d curl.cainfo=${__PROJECT__}/bin/runtime/cacert.pem -d openssl.cafile=${__PROJECT__}/bin/runtime/cacert.pem' "
echo " OR "
echo " alias php='php -c ${__PROJECT__}/bin/runtime/php.ini' "
echo " "
export PATH="${__PROJECT__}/bin/runtime:$PATH"
