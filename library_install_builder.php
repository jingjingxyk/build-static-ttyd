<?php


use SwooleCli\Library;
use SwooleCli\Preprocessor;

// ================================================================================================
// Library
// ================================================================================================

/**
 * cmake use static openssl
 *
 * set(OPENSSL_USE_STATIC_LIBS TRUE)
 * find_package(OpenSSL REQUIRED)
 * target_link_libraries(program OpenSSL::Crypto)
 */

function install_openssl(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('openssl'))
            ->withUrl('https://www.openssl.org/source/openssl-1.1.1p.tar.gz')
            ->withPrefix(OPENSSL_PREFIX)
            ->withConfigure(
                './config' . ($p->getOsType(
                ) === 'macos' ? '' : ' -static --static') . ' no-shared --prefix=' . OPENSSL_PREFIX
            )
            ->withMakeInstallCommand('install_sw')
            ->withLicense('https://github.com/openssl/openssl/blob/master/LICENSE.txt', Library::LICENSE_APACHE2)
            ->withHomePage('https://www.openssl.org/')
            ->withPkgName('openssl')
    );
}

function install_libiconv(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('libiconv'))
            ->withUrl('https://ftp.gnu.org/pub/gnu/libiconv/libiconv-1.16.tar.gz')
            ->withPrefix(ICONV_PREFIX)
            ->withPkgConfig('')
            ->withConfigure('./configure --prefix=' . ICONV_PREFIX . ' enable_static=yes enable_shared=no')
            ->withLicense('https://www.gnu.org/licenses/old-licenses/gpl-2.0.html', Library::LICENSE_GPL)
    );
}


// Dependent libiconv
function install_libxml2(Preprocessor $p)
{
    $libxml2_prefix = LIBXML2_PREFIX;
    $iconv_prefix = ICONV_PREFIX;
    $p->addLibrary(
        (new Library('libxml2'))
            ->withUrl('https://gitlab.gnome.org/GNOME/libxml2/-/archive/v2.9.10/libxml2-v2.9.10.tar.gz')
            ->withPrefix(LIBXML2_PREFIX)
            ->withConfigure(
                <<<EOF
./autogen.sh && ./configure --prefix=$libxml2_prefix --with-iconv=$iconv_prefix --enable-static=yes --enable-shared=no --without-python
EOF
            )
            ->withPkgName('libxml-2.0')
            ->withLicense('https://www.opensource.org/licenses/mit-license.html', Library::LICENSE_MIT)
            ->depends('libiconv')
    );
}

// Dependent libxml2
function install_libxslt(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('libxslt'))
            ->withUrl('https://gitlab.gnome.org/GNOME/libxslt/-/archive/v1.1.34/libxslt-v1.1.34.tar.gz')
            ->withPrefix(LIBXSLT_PREFIX)
            ->withConfigure(
                './autogen.sh && ./configure --prefix=' . LIBXSLT_PREFIX . ' --enable-static=yes --enable-shared=no'
            )
            ->withLicense('http://www.opensource.org/licenses/mit-license.html', Library::LICENSE_MIT)
            ->withPkgName('libexslt libxslt')
            ->depends('libxml2', 'libiconv')
    );
}


function install_brotli(Preprocessor $p)
{
    /*
    -DCMAKE_BUILD_TYPE="${BUILD_TYPE}" \
    -DCMAKE_INSTALL_PREFIX="${PREFIX}" \
    -DCMAKE_INSTALL_LIBDIR="${LIBDIR}" \
  */
    $brotli_prefix = BROTLI_PREFIX;
    $p->addLibrary(
        (new Library('brotli'))
            ->withManual('https://github.com/google/brotli')//有多种构建方式，选择cmake 构建
            ->withUrl('https://github.com/google/brotli/archive/refs/tags/v1.0.9.tar.gz')
            ->withFile('brotli-1.0.9.tar.gz')
            ->withPrefix($brotli_prefix)
            ->withCleanBuildDirectory()
            ->withCleanInstallDirectory($brotli_prefix)
            ->withConfigure(
                <<<EOF
            cmake . -DCMAKE_BUILD_TYPE=Release \
            -DCMAKE_INSTALL_PREFIX={$brotli_prefix} \
            -DBROTLI_SHARED_LIBS=OFF \
            -DBROTLI_STATIC_LIBS=ON \
            -DBROTLI_DISABLE_TESTS=ON \
            -DBROTLI_BUNDLED_MODE=OFF 
                
            cmake --build . --config Release --target install
EOF
            )
            ->withSkipMakeAndMakeInstall()
            ->withScriptAfterInstall(
                implode(PHP_EOL, [
                    'rm -rf ' . BROTLI_PREFIX . '/lib/*.so.*',
                    'rm -rf ' . BROTLI_PREFIX . '/lib/*.so',
                    'rm -rf ' . BROTLI_PREFIX . '/lib/*.dylib',
                    'cp ' . BROTLI_PREFIX . '/lib/libbrotlicommon-static.a ' . BROTLI_PREFIX . '/lib/libbrotli.a',
                    'mv ' . BROTLI_PREFIX . '/lib/libbrotlicommon-static.a ' . BROTLI_PREFIX . '/lib/libbrotlicommon.a',
                    'mv ' . BROTLI_PREFIX . '/lib/libbrotlienc-static.a ' . BROTLI_PREFIX . '/lib/libbrotlienc.a',
                    'mv ' . BROTLI_PREFIX . '/lib/libbrotlidec-static.a ' . BROTLI_PREFIX . '/lib/libbrotlidec.a'
                ])
            )
            ->withPkgName('libbrotlicommon libbrotlidec libbrotlienc')
            ->withLicense('https://github.com/google/brotli/blob/master/LICENSE', Library::LICENSE_MIT)
            ->withHomePage('https://github.com/google/brotli')
    );
}

function install_cares(Preprocessor $p)
{
    $cares_prefix = CARES_PREFIX;
    $p->addLibrary(
        (new Library('cares'))
            ->withUrl('https://c-ares.org/download/c-ares-1.19.0.tar.gz')
            ->withPrefix($cares_prefix)
            ->withConfigure("./configure --prefix={$cares_prefix} --enable-static --disable-shared")
            ->withPkgName('libcares')
            ->withLicense('https://c-ares.org/license.html', Library::LICENSE_MIT)
            ->withHomePage('https://c-ares.org/')
    );
}

function install_gmp(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('gmp'))
            ->withUrl('https://gmplib.org/download/gmp/gmp-6.2.1.tar.lz')
            ->withPrefix(GMP_PREFIX)
            ->withConfigure('./configure --prefix=' . GMP_PREFIX . ' --enable-static --disable-shared')
            ->withLicense('https://www.gnu.org/licenses/old-licenses/gpl-2.0.html', Library::LICENSE_GPL)
            ->withPkgName('gmp')
    );
}


/*
// CFLAGS="-static -O2 -Wall" \
// LDFLAGS="-Wl,R-lncurses"
// LDFLAGS="-lncurses" \
 */
function install_ncurses(Preprocessor $p)
{
    $ncurses_prefix = NCURSES_PREFIX;
    $p->addLibrary(
        (new Library('ncurses'))
            ->withUrl('https://ftp.gnu.org/pub/gnu/ncurses/ncurses-6.3.tar.gz')
            ->withMirrorUrl('https://mirrors.tuna.tsinghua.edu.cn/gnu/ncurses/ncurses-6.3.tar.gz')
            ->withMirrorUrl('https://mirrors.ustc.edu.cn/gnu/ncurses/ncurses-6.3.tar.gz')
            ->withPrefix(NCURSES_PREFIX)
            ->withConfigure(
                <<<EOF
            mkdir -p {$ncurses_prefix}/lib/pkgconfig
            ./configure \
            --prefix={$ncurses_prefix} \
            --enable-static \
            --disable-shared \
            --enable-pc-files \
            --with-pkg-config={$ncurses_prefix}/lib/pkgconfig \
            --with-pkg-config-libdir={$ncurses_prefix}/lib/pkgconfig \
            --with-normal \
            --enable-widec \
            --enable-echo \
            --with-ticlib  \
            --without-termlib \
            --enable-sp-funcs \
            --enable-term-driver \
            --enable-ext-colors \
            --enable-ext-mouse \
            --enable-ext-putwin \
            --enable-no-padding \
            --without-debug \
            --without-tests \
            --without-dlsym \
            --without-debug \
            --enable-symlinks
EOF
            )
            ->withScriptBeforeInstall(
                '
                ln -s ' . NCURSES_PREFIX . '/lib/pkgconfig/formw.pc ' . NCURSES_PREFIX . '/lib/pkgconfig/form.pc ;
                ln -s ' . NCURSES_PREFIX . '/lib/pkgconfig/menuw.pc ' . NCURSES_PREFIX . '/lib/pkgconfig/menu.pc ;
                ln -s ' . NCURSES_PREFIX . '/lib/pkgconfig/ncurses++w.pc ' . NCURSES_PREFIX . '/lib/pkgconfig/ncurses++.pc ;
                ln -s ' . NCURSES_PREFIX . '/lib/pkgconfig/ncursesw.pc ' . NCURSES_PREFIX . '/lib/pkgconfig/ncurses.pc ;
                ln -s ' . NCURSES_PREFIX . '/lib/pkgconfig/panelw.pc ' . NCURSES_PREFIX . '/lib/pkgconfig/panel.pc ;
                ln -s ' . NCURSES_PREFIX . '/lib/pkgconfig/ticw.pc ' . NCURSES_PREFIX . '/lib/pkgconfig/tic.pc ;

                ln -s ' . NCURSES_PREFIX . '/lib/libformw.a ' . NCURSES_PREFIX . '/lib/libform.a ;
                ln -s ' . NCURSES_PREFIX . '/lib/libmenuw.a ' . NCURSES_PREFIX . '/lib/libmenu.a ;
                ln -s ' . NCURSES_PREFIX . '/lib/libncurses++w.a ' . NCURSES_PREFIX . '/lib/libncurses++.a ;
                ln -s ' . NCURSES_PREFIX . '/lib/libncursesw.a ' . NCURSES_PREFIX . '/lib/libncurses.a ;
                ln -s ' . NCURSES_PREFIX . '/lib/libpanelw.a  ' . NCURSES_PREFIX . '/lib/libpanel.a ;
                ln -s ' . NCURSES_PREFIX . '/lib/libticw.a ' . NCURSES_PREFIX . '/lib/libtic.a ;
            '
            )
            ->withPkgName('ncursesw')
            ->withLicense('https://github.com/projectceladon/libncurses/blob/master/README', Library::LICENSE_MIT)
            ->withHomePage('https://github.com/projectceladon/libncurses')
    );
}


function install_readline(Preprocessor $p)
{
    $readline_prefix = READLINE_PREFIX;
    $p->addLibrary(
        (new Library('readline'))
            ->withUrl('https://ftp.gnu.org/gnu/readline/readline-8.2.tar.gz')
            ->withMirrorUrl('https://mirrors.tuna.tsinghua.edu.cn/gnu/readline/readline-8.2.tar.gz')
            ->withMirrorUrl('https://mirrors.ustc.edu.cn/gnu/readline/readline-8.2.tar.gz')
            ->withPrefix(READLINE_PREFIX)
            ->withConfigure(
                <<<EOF
                ./configure \
                --prefix={$readline_prefix} \
                --enable-static \
                --disable-shared \
                --with-curses \
                --enable-multibyte 
EOF
            )
            ->withPkgName('readline')
            ->withLdflags('-L' . READLINE_PREFIX . '/lib')
            ->withLicense('https://www.gnu.org/licenses/gpl.html', Library::LICENSE_GPL)
            ->withHomePage('https://tiswww.case.edu/php/chet/readline/rltop.html')
            ->depends('ncurses')
    );
}

/*
            ZIP_CFLAGS=$(pkg-config --cflags libzip) ;
            ZIP_LIBS=$(pkg-config --libs libzip) ;
            ZLIB_CFLAGS=$(pkg-config --cflags zlib) ;
            ZLIB_LIBS=$(pkg-config --libs zlib) ;
            LIBZSTD_CFLAGS=$(pkg-config --cflags libzstd) ;
            LIBZSTD_LIBS=$(pkg-config --libs libzstd) ;
            FREETYPE_CFLAGS=$(pkg-config --cflags freetype2) ;
            FREETYPE_LIBS=$(pkg-config --libs freetype2) ;
            LZMA_CFLAGS=$(pkg-config --cflags liblzma) ;
            LZMA_LIBS=$(pkg-config --libs liblzma) ;
            PNG_CFLAGS=$(pkg-config --cflags libpng  libpng16) ;
            PNG_LIBS=$(pkg-config --libs libpng  libpng16) ;
            WEBP_CFLAGS=$(pkg-config --cflags libwebp ) ;
            WEBP_LIBS=$(pkg-config --libs libwebp ) ;
            WEBPMUX_CFLAGS=$(pkg-config --cflags libwebp libwebpdemux  libwebpmux) ;
            WEBPMUX_LIBS=$(pkg-config --libs libwebp libwebpdemux  libwebpmux) ;
            XML_CFLAGS=$(pkg-config --cflags libxml-2.0) ;
            XML_LIBS=$(pkg-config --libs libxml-2.0) ;
 */


function install_libyaml(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('libyaml'))
            ->withUrl('https://pyyaml.org/download/libyaml/yaml-0.2.5.tar.gz')
            ->withPrefix(LIBYAML_PREFIX)
            ->withConfigure('./configure --prefix=' . LIBYAML_PREFIX . ' --enable-static --disable-shared')
            ->withPkgName('yaml-0.1')
            ->withLicense('https://pyyaml.org/wiki/LibYAML', Library::LICENSE_MIT)
            ->withHomePage('https://pyyaml.org/wiki/LibYAML')
    );
}

function install_libsodium(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('libsodium'))
            // ISC License, like BSD
            ->withLicense('https://en.wikipedia.org/wiki/ISC_license', Library::LICENSE_SPEC)
            ->withHomePage('https://doc.libsodium.org/')
            ->withUrl('https://download.libsodium.org/libsodium/releases/libsodium-1.0.18.tar.gz')
            ->withPrefix(LIBSODIUM_PREFIX)
            ->withConfigure('./configure --prefix=' . LIBSODIUM_PREFIX . ' --enable-static --disable-shared')
            ->withPkgName('libsodium')
    );
}

function install_bzip2(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('bzip2'))
            ->withUrl('https://sourceware.org/pub/bzip2/bzip2-1.0.8.tar.gz')
            ->withPrefix(BZIP2_PREFIX)
            ->withMakeOptions('PREFIX=' . BZIP2_PREFIX)
            ->withMakeInstallOptions('PREFIX=' . BZIP2_PREFIX)
            ->withHomePage('https://www.sourceware.org/bzip2/')
            ->withLicense('https://www.sourceware.org/bzip2/', Library::LICENSE_BSD)
    );
}

function install_zlib(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('zlib'))
            //->withUrl('https://zlib.net/zlib-1.2.13.tar.gz')
            ->withUrl('https://udomain.dl.sourceforge.net/project/libpng/zlib/1.2.11/zlib-1.2.11.tar.gz')
            ->withPrefix(ZLIB_PREFIX)
            ->withConfigure('./configure --prefix=' . ZLIB_PREFIX . ' --static')
            ->withHomePage('https://zlib.net/')
            ->withLicense('https://zlib.net/zlib_license.html', Library::LICENSE_SPEC)
            ->withPkgName('zlib')
            ->depends('libxml2', 'bzip2')
    );
}


function install_liblz4(Preprocessor $p)
{
    $liblz4_prefix = LIBLZ4_PREFIX;
    $p->addLibrary(
        (new Library('liblz4'))
            ->withHomePage('http://www.lz4.org')
            ->withLicense('https://github.com/lz4/lz4/blob/dev/LICENSE', Library::LICENSE_BSD)
            ->withUrl('https://github.com/lz4/lz4/archive/refs/tags/v1.9.4.tar.gz')
            ->withFile('lz4-v1.9.4.tar.gz')
            ->withPkgName('liblz4')
            ->withPrefix($liblz4_prefix)
            ->withConfigure(
                <<<EOF
            cd build/cmake/
            cmake . -DCMAKE_INSTALL_PREFIX={$liblz4_prefix}  -DBUILD_SHARED_LIBS=OFF  -DBUILD_STATIC_LIBS=ON
EOF
            )
    );

    //可以使用CMAKE 编译 也可以
    //不使用CMAKE，需要自己修改安装目录
    //->withMakeOptions('INSTALL_PROGRAM=/usr/liblz4/')
    //->withMakeInstallOptions("DESTDIR=/usr/liblz4/")
}


function install_liblzma(Preprocessor $p)
{
    $liblzma_prefix = LIBLZMA_PREFIX;
    $p->addLibrary(
        (new Library('liblzma'))
            ->withHomePage('https://tukaani.org/xz/')
            ->withLicense('https://github.com/tukaani-project/xz/blob/master/COPYING.GPLv3', Library::LICENSE_LGPL)
            //->withUrl('https://tukaani.org/xz/xz-5.2.9.tar.gz')
            //->withFile('xz-5.2.9.tar.gz')
            ->withUrl('https://github.com/tukaani-project/xz/releases/download/v5.4.1/xz-5.4.1.tar.gz')
            ->withFile('xz-5.4.1.tar.gz')
            ->withPrefix($liblzma_prefix)
            ->withConfigure(
                './configure --prefix=' . $liblzma_prefix . ' --enable-static  --disable-shared --disable-doc'
            )
            ->withPkgName('liblzma')
    );
}


function install_libzstd(Preprocessor $p)
{
    $libzstd_prefix = LIBZSTD_PREFIX;
    $p->addLibrary(
        (new Library('libzstd'))
            ->withHomePage('https://github.com/facebook/zstd')
            ->withLicense('https://github.com/facebook/zstd/blob/dev/COPYING', Library::LICENSE_GPL)
            ->withUrl('https://github.com/facebook/zstd/releases/download/v1.5.2/zstd-1.5.2.tar.gz')
            ->withFile('zstd-1.5.2.tar.gz')
            ->withPrefix($libzstd_prefix)
            ->withConfigure(
                <<<EOF
            mkdir -p build/cmake/builddir
            cd build/cmake/builddir
            cmake .. \
            -DCMAKE_INSTALL_PREFIX={$libzstd_prefix} \
            -DZSTD_BUILD_STATIC=ON \
            -DCMAKE_BUILD_TYPE=Release \
            -DZSTD_BUILD_CONTRIB=ON \
            -DZSTD_BUILD_PROGRAMS=ON \
            -DZSTD_BUILD_SHARED=OFF \
            -DZSTD_BUILD_TESTS=OFF \
            -DZSTD_LEGACY_SUPPORT=ON 
EOF
            )
            ->withMakeOptions('lib')
            //->withMakeInstallOptions('install PREFIX=/usr/libzstd/')
            ->withPkgName('libzstd')
            ->depends('liblz4', 'liblzma')
    );
    /*
               '
           mkdir -p build/cmake/builddir
           cd build/cmake/builddir
           # cmake -LH ..
           cmake .. \
           -DCMAKE_INSTALL_PREFIX=/usr/libzstd/ \
           -DZSTD_BUILD_STATIC=ON \
           -DCMAKE_BUILD_TYPE=Release \
           -DZSTD_BUILD_CONTRIB=ON \
           -DZSTD_BUILD_PROGRAMS=OFF \
           -DZSTD_BUILD_SHARED=OFF \
           -DZSTD_BUILD_TESTS=OFF \
           -DZSTD_LEGACY_SUPPORT=ON \
           \
           -DZSTD_ZLIB_SUPPORT=ON \
           -DZLIB_INCLUDE_DIR=/usr/zlib/include \
           -DZLIB_LIBRARY=/usr/zlib/lib \
           \
           -DZSTD_LZ4_SUPPORT=ON \
           -DLIBLZ4_INCLUDE_DIR=/usr/liblz4/include \
           -DLIBLZ4_LIBRARY=/usr/liblz4/lib \
           \
           -DZSTD_LZMA_SUPPORT=ON \
           -DLIBLZMA_LIBRARY=/usr/liblzma/lib \
           -DLIBLZMA_INCLUDE_DIR=/usr/liblzma/include \
           -DLIBLZMA_HAS_AUTO_DECODER=ON\
           -DLIBLZMA_HAS_EASY_ENCODER=ON \
           -DLIBLZMA_HAS_LZMA_PRESET=ON
           '
    */
}


// MUST be in the /usr directory
function install_zip(Preprocessor $p)
{
    $openssl_prefix = OPENSSL_PREFIX;
    $zip_prefix = ZIP_PREFIX;
    $liblzma_prefix = LIBLZ4_PREFIX;
    $libzstd_prefix = LIBZSTD_PREFIX;
    $zlib_prefix = ZLIB_PREFIX;
    $bzip2_prefix = BZIP2_PREFIX;
    $p->addLibrary(
        (new Library('zip'))
            //->withUrl('https://libzip.org/download/libzip-1.8.0.tar.gz')
            ->withUrl('https://libzip.org/download/libzip-1.9.2.tar.gz')
            ->withManual('https://libzip.org')
            ->withPrefix($zip_prefix)
            ->withCleanBuildDirectory()
            ->withCleanInstallDirectory($zip_prefix)
            ->withConfigure(
                <<<EOF
            cmake -Wno-dev .  \
            -DCMAKE_INSTALL_PREFIX={$zip_prefix} \
            -DCMAKE_BUILD_TYPE=optimized \
            -DBUILD_TOOLS=OFF \
            -DBUILD_EXAMPLES=OFF \
            -DBUILD_DOC=OFF \
            -DLIBZIP_DO_INSTALL=ON \
            -DBUILD_SHARED_LIBS=OFF \
            -DENABLE_GNUTLS=OFF  \
            -DENABLE_MBEDTLS=OFF \
            -DENABLE_OPENSSL=ON \
            -DOPENSSL_USE_STATIC_LIBS=TRUE \
            -DOPENSSL_LIBRARIES={$openssl_prefix}/lib \
            -DOPENSSL_INCLUDE_DIR={$openssl_prefix}/include \
            -DZLIB_LIBRARY={$zlib_prefix}/lib \
            -DZLIB_INCLUDE_DIR={$zlib_prefix}/include \
            -DENABLE_BZIP2=ON \
            -DBZIP2_LIBRARIES={$bzip2_prefix}/lib \
            -DBZIP2_LIBRARY={$bzip2_prefix}/lib \
            -DBZIP2_INCLUDE_DIR={$bzip2_prefix}/include \
            -DBZIP2_NEED_PREFIX=ON \
            -DENABLE_LZMA=ON  \
            -DLIBLZMA_LIBRARY={$liblzma_prefix}/lib \
            -DLIBLZMA_INCLUDE_DIR={$liblzma_prefix}/include \
            -DLIBLZMA_HAS_AUTO_DECODER=ON  \
            -DLIBLZMA_HAS_EASY_ENCODER=ON  \
            -DLIBLZMA_HAS_LZMA_PRESET=ON \
            -DENABLE_ZSTD=ON \
            -DZstd_LIBRARY={$libzstd_prefix}/lib \
            -DZstd_INCLUDE_DIR={$libzstd_prefix}/include
EOF
            )
            ->withMakeOptions('VERBOSE=1')
            ->withPkgName('libzip')
            ->withHomePage('https://libzip.org/')
            ->withLicense('https://libzip.org/license/', Library::LICENSE_BSD)
            ->depends('openssl', 'zlib', 'bzip2', 'liblzma', 'libzstd')
    );
}


function install_sqlite3(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('sqlite3'))
            ->withUrl('https://www.sqlite.org/2021/sqlite-autoconf-3370000.tar.gz')
            ->withPrefix(SQLITE3_PREFIX)
            ->withConfigure('./configure --prefix=' . SQLITE3_PREFIX . ' --enable-static --disable-shared')
            ->withHomePage('https://www.sqlite.org/index.html')
            ->withLicense('https://www.sqlite.org/copyright.html', Library::LICENSE_SPEC)
            ->withPkgName('sqlite3')
    );
}


function install_icu(Preprocessor $p)
{
    $icu_prefix = ICU_PREFIX;
    $os = $p->getOsType() == 'macos' ? 'MacOSX' : 'Linux';
    $p->addLibrary(
        (new Library('icu'))
            ->withUrl('https://github.com/unicode-org/icu/releases/download/release-60-3/icu4c-60_3-src.tgz')
            //->withUrl('https://github.com/unicode-org/icu/releases/download/release-72-1/icu4c-72_1-src.tgz')
            ->withManual("https://unicode-org.github.io/icu/userguide/icu4c/build.html")
            ->withCleanBuildDirectory()
            ->withPrefix(ICU_PREFIX)
            ->withConfigure(
                <<<EOF
             export CPPFLAGS="-DU_CHARSET_IS_UTF8=1  -DU_USING_ICU_NAMESPACE=1  -DU_STATIC_IMPLEMENTATION=1"
             source/runConfigureICU $os --prefix={$icu_prefix} \
             --enable-icu-config=yes \
             --enable-static=yes \
             --enable-shared=no \
             --with-data-packaging=archive \
             --enable-release=yes \
             --enable-extras=yes \
             --enable-icuio=yes \
             --enable-dyload=no \
             --enable-tools=yes \
             --enable-tests=no \
             --enable-samples=no
EOF
            )
            ->withPkgName('icu-i18n  icu-io   icu-uc')
            ->withHomePage('https://icu.unicode.org/')
            ->withLicense('https://github.com/unicode-org/icu/blob/main/icu4c/LICENSE', Library::LICENSE_SPEC)
    );
}

function install_oniguruma(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('oniguruma'))
            ->withUrl('https://codeload.github.com/kkos/oniguruma/tar.gz/refs/tags/v6.9.7')
            ->withPrefix(ONIGURUMA_PREFIX)
            ->withConfigure(
                './autogen.sh && ./configure --prefix=' . ONIGURUMA_PREFIX . ' --enable-static --disable-shared'
            )
            ->withFile('oniguruma-6.9.7.tar.gz')
            ->withLicense('https://github.com/kkos/oniguruma/blob/master/COPYING', Library::LICENSE_SPEC)
            ->withPkgName('oniguruma')
    );
}

function install_mimalloc(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('mimalloc'))
            ->withUrl('https://github.com/microsoft/mimalloc/archive/refs/tags/v2.0.7.tar.gz')
            ->withFile('mimalloc-2.0.7.tar.gz')
            ->withPrefix(MIMALLOC_PREFIX)
            ->withConfigure(
                'cmake . -DMI_BUILD_SHARED=OFF -DCMAKE_INSTALL_PREFIX=' . MIMALLOC_PREFIX . ' -DMI_INSTALL_TOPLEVEL=ON -DMI_PADDING=OFF -DMI_SKIP_COLLECT_ON_EXIT=ON -DMI_BUILD_TESTS=OFF'
            )
            ->withPkgName('libmimalloc')
            ->withLicense('https://github.com/microsoft/mimalloc/blob/master/LICENSE', Library::LICENSE_MIT)
            ->withHomePage('https://microsoft.github.io/mimalloc/')
            ->withLdflags('-L' . MIMALLOC_PREFIX . '/lib -lmimalloc')
    );
}


function install_libjpeg(Preprocessor $p)
{
    $lib = new Library('libjpeg');
    $lib->withHomePage('https://libjpeg-turbo.org/')
        ->withLicense('https://github.com/libjpeg-turbo/libjpeg-turbo/blob/main/LICENSE.md', Library::LICENSE_BSD)
        ->withUrl('https://codeload.github.com/libjpeg-turbo/libjpeg-turbo/tar.gz/refs/tags/2.1.2')
        ->withFile('libjpeg-turbo-2.1.2.tar.gz')
        ->withPrefix(JPEG_PREFIX)
        ->withConfigure('cmake -G"Unix Makefiles" -DCMAKE_INSTALL_PREFIX=' . JPEG_PREFIX . ' .')
        ->withPkgName('libjpeg');

    // linux 系统中是保存在 /usr/lib64 目录下的，而 macos 是放在 /usr/lib 目录中的，不清楚这里是什么原因？
    $jpeg_lib_dir = JPEG_PREFIX . '/' . ($p->getOsType() === 'macos' ? 'lib' : 'lib64');
    $gif_prefix = GIF_PREFIX;
    $lib->withLdflags('-L' . $jpeg_lib_dir)
        ->withPkgConfig($jpeg_lib_dir . '/pkgconfig');
    if ($p->getOsType() === 'macos') {
        $lib->withScriptAfterInstall('find ' . $lib->prefix . ' -name \*.dylib | xargs rm -f');
    }
    $p->addLibrary($lib);
}


function install_libgif(Preprocessor $p)
{
    $gif_prefix = GIF_PREFIX;
    $p->addLibrary(
        (new Library('libgif'))
            ->withUrl('https://nchc.dl.sourceforge.net/project/giflib/giflib-5.2.1.tar.gz')
            ->withLicense('https://giflib.sourceforge.net/intro.html', Library::LICENSE_SPEC)
            ->withPrefix(GIF_PREFIX)
            ->withMakeOptions('libgif.a')
            ->withMakeInstallCommand('')
            ->withScriptAfterInstall(
                <<<EOF
                if [ ! -d {$gif_prefix}/lib ]; then
                    mkdir -p {$gif_prefix}/lib
                fi
                if [ ! -d {$gif_prefix}/include ]; then
                    mkdir -p {$gif_prefix}/include
                fi
                cp libgif.a {$gif_prefix}/lib/libgif.a
                cp gif_lib.h {$gif_prefix}/include/gif_lib.h
                EOF
            )
            ->withLdflags('-L' . GIF_PREFIX . '/lib')
            ->withPkgName('')
            ->withPkgConfig('')
    );
    if (0) {
        $p->addLibrary(
            (new Library('giflib'))
                ->withUrl('https://nchc.dl.sourceforge.net/project/giflib/giflib-5.2.1.tar.gz')
                ->withLicense('http://giflib.sourceforge.net/intro.html', Library::LICENSE_SPEC)
                ->withCleanBuildDirectory()
                ->withPrefix('/usr/giflib')
                ->withScriptBeforeConfigure(
                    '
    
                default_prefix_dir="/ u s r" # 阻止 macos 系统下编译路径被替换
                # 替换空格
                default_prefix_dir=$(echo "$default_prefix_dir" | sed -e "s/[ ]//g")
                
                sed -i.bakup "s@PREFIX = $default_prefix_dir/local@PREFIX = /usr/giflib@" Makefile
           
                cat >> Makefile <<"EOF"
install-lib-static:
    $(INSTALL) -d "$(DESTDIR)$(LIBDIR)"
    $(INSTALL) -m 644 libgif.a "$(DESTDIR)$(LIBDIR)/libgif.a"
EOF
              
               
                '
                )
                ->withMakeOptions('libgif.a')
                //->withMakeOptions('all')
                ->withMakeInstallOptions('install-include && make  install-lib-static')
                # ->withMakeInstallCommand('install-include DESTDIR=/usr/giflib && make  install-lib-static DESTDIR=/usr/giflib')
                # ->withMakeInstallOptions('DESTDIR=/usr/libgif')
                ->withLdflags('-L/usr/giflib/lib')
                ->disableDefaultPkgConfig()
        );
    }
}

function install_libpng(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('libpng'))
            ->withUrl('https://nchc.dl.sourceforge.net/project/libpng/libpng16/1.6.37/libpng-1.6.37.tar.gz')
            ->withLicense('http://www.libpng.org/pub/png/src/libpng-LICENSE.txt', Library::LICENSE_SPEC)
            ->withPrefix(PNG_PREFIX)
            ->withConfigure(
                './configure --prefix=' . PNG_PREFIX . ' --enable-static --disable-shared ' .
                '--with-zlib-prefix=' . ZLIB_PREFIX . '  --with-binconfigs'
            )
            ->withPkgName('libpng16')
            ->depends('zlib')
    );
}


function install_libwebp(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('libwebp'))
            ->withUrl('https://codeload.github.com/webmproject/libwebp/tar.gz/refs/tags/v1.2.1')
            ->withFile('libwebp-1.2.1.tar.gz')
            ->withHomePage('https://github.com/webmproject/libwebp')
            ->withLicense('https://github.com/webmproject/libwebp/blob/main/COPYING', Library::LICENSE_SPEC)
            ->withPrefix(WEBP_PREFIX)
            ->withConfigure(
                './autogen.sh && ./configure --prefix=' . WEBP_PREFIX . ' --enable-static --disable-shared ' .
                '--enable-libwebpdecoder ' .
                '--enable-libwebpextras ' .
                '--with-pngincludedir=' . PNG_PREFIX . '/include ' .
                '--with-pnglibdir=' . PNG_PREFIX . '/lib ' .
                '--with-jpegincludedir=' . JPEG_PREFIX . '/include ' .
                '--with-jpeglibdir=' . JPEG_PREFIX . ' ' .
                '--with-gifincludedir=' . GIF_PREFIX . '/include ' .
                '--with-giflibdir=' . GIF_PREFIX . '/lib'
            )
            ->withPkgName('libwebp')
            ->withLdflags('-L' . WEBP_PREFIX . '/lib -lwebpdemux -lwebpmux')
            ->depends('libpng', 'libjpeg', 'libgif')
    );
}


function install_freetype(Preprocessor $p)
{
    $freetype_prefix = FREETYPE_PREFIX;
    $bzip2_prefix = BZIP2_PREFIX;
    $p->addLibrary(
        (new Library('freetype'))
            ->withPrefix($freetype_prefix)
            ->withUrl('https://download.savannah.gnu.org/releases/freetype/freetype-2.10.4.tar.gz')
            ->withLicense(
                'https://gitlab.freedesktop.org/freetype/freetype/-/blob/master/docs/FTL.TXT',
                Library::LICENSE_SPEC
            )
            ->withCleanBuildDirectory()
            ->withCleanInstallDirectory($freetype_prefix)
            ->withConfigure(
                <<<EOF
            ./configure --help 
            BZIP2_CFLAGS="-I{$bzip2_prefix}/include"  \
            BZIP2_LIBS="-L{$bzip2_prefix}/lib -lbz2"  \
            CPPFLAGS="$(pkg-config --cflags-only-I --static zlib libpng  libbrotlicommon  libbrotlidec  libbrotlienc)" \
            LDFLAGS="$(pkg-config  --libs-only-L   --static zlib libpng  libbrotlicommon  libbrotlidec  libbrotlienc)" \
            LIBS="$(pkg-config     --libs-only-l   --static zlib libpng  libbrotlicommon  libbrotlidec  libbrotlienc)" \
            ./configure --prefix={$freetype_prefix} \
            --enable-static \
            --disable-shared \
            --with-zlib=yes \
            --with-bzip2=yes \
            --with-png=yes \
            --with-harfbuzz=no \
            --with-brotli=yes 
EOF
            )
            ->withHomePage('https://freetype.org/')
            ->withPkgName('freetype2')
            ->depends('zlib', 'bzip2', 'libpng', 'brotli')
    );
}


function install_imagemagick(Preprocessor $p)
{
    $imagemagick_prefix = IMAGEMAGICK_PREFIX;
    $p->addLibrary(
        (new Library('imagemagick'))
            ->withUrl('https://github.com/ImageMagick/ImageMagick/archive/refs/tags/7.1.0-62.tar.gz')
            ->withPrefix($imagemagick_prefix)
            ->withCleanBuildDirectory()
            ->withCleanInstallDirectory($imagemagick_prefix)
            ->withFile('ImageMagick-v7.1.0-62.tar.gz')
            ->withPrefix($imagemagick_prefix)
            ->withConfigure(
                <<<EOF
            ./configure --help   
            CPPFLAGS="$(pkg-config --cflags-only-I --static libzip zlib libzstd freetype2 libxml-2.0 liblzma openssl libjpeg  libturbojpeg libpng libwebp  libwebpdecoder  libwebpdemux  libwebpmux)" \
            LDFLAGS="$(pkg-config  --libs-only-L   --static libzip zlib libzstd freetype2 libxml-2.0 liblzma openssl libjpeg  libturbojpeg libpng libwebp  libwebpdecoder  libwebpdemux  libwebpmux)" \
            LIBS="$(pkg-config     --libs-only-l   --static libzip zlib libzstd freetype2 libxml-2.0 liblzma openssl libjpeg  libturbojpeg libpng libwebp  libwebpdecoder  libwebpdemux  libwebpmux)" \
            ./configure \
            --prefix={$imagemagick_prefix} \
            --enable-static \
            --disable-shared \
            --with-zip=yes \
            --with-fontconfig=no \
            --with-heic=no \
            --with-lcms=no \
            --with-lqr=no \
            --with-openexr=no \
            --with-openjp2=no \
            --with-pango=no \
            --with-jpeg=yes \
            --with-png=yes \
            --with-webp=yes \
            --with-raw=yes \
            --with-tiff=yes \
            --with-zstd=yes \
            --with-lzma=yes \
            --with-xml=yes \
            --with-zip=yes \
            --with-zlib=yes \
            --with-zstd=yes \
            --with-freetype=yes 

EOF
            )
            ->withPkgName('ImageMagick')
            ->withLicense('https://imagemagick.org/script/license.php', Library::LICENSE_APACHE2)
            ->depends(
                'libxml2',
                'libzip',
                'zlib',
                'libjpeg',
                'freetype',
                'libwebp',
                'libpng',
                'libgif',
                'openssl',
                'libzstd'
            )
    );
}


function install_libidn2(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('libidn2'))
            ->withUrl('https://ftp.gnu.org/gnu/libidn/libidn2-2.3.4.tar.gz')
            ->withLicense('https://www.gnu.org/licenses/old-licenses/gpl-2.0.html', Library::LICENSE_GPL)
            ->withCleanBuildDirectory()
            ->withPrefix('/usr/libidn2')
            ->withScriptBeforeConfigure(
                '
            test -d /usr/libidn2 && rm -rf /usr/libidn2
            
            apk add  gettext  coreutils
           
            '
            )
            ->withConfigure(
                '
            ./configure --help 
            
            #  intl  依赖  gettext
            
            ./configure --prefix=/usr/libidn2 enable_static=yes enable_shared=no \
             --disable-doc \
             --with-libiconv-prefix=/usr/libiconv \
             --with-libintl-prefix
             
            '
            )
            ->withPkgName('libidn2')
    );
}


/**
 *
 * -lz      压缩库（Z）
 *
 * -lrt     实时库（real time）：shm_open系列
 *
 * -lm     数学库（math）
 *
 * -lc     标准C库（C lib）
 *
 * -dl ，是显式加载动态库的动态函数库
 *
 */
/**
 * cur  交叉编译
 *
 * https://curl.se/docs/install.html
 *
 * export PATH=$PATH:/opt/hardhat/devkit/ppc/405/bin
 * export CPPFLAGS="-I/opt/hardhat/devkit/ppc/405/target/usr/include"
 * export AR=ppc_405-ar
 * export AS=ppc_405-as
 * export LD=ppc_405-ld
 * export RANLIB=ppc_405-ranlib
 * export CC=ppc_405-gcc
 * export NM=ppc_405-nm
 * --with-random=/dev/urandom
 *
 * randlib
 * strip
 *
 */
function install_curl(Preprocessor $p)
{
    //http3 有多个实现
    //参考文档： https://curl.se/docs/http3.html
    //https://curl.se/docs/protdocs.html
    $curl_prefix = CURL_PREFIX;
    $openssl_prefix = OPENSSL_PREFIX;
    $zlib_prefix = ZLIB_PREFIX;

    $libidn2_prefix = LIBIDN2_PREFIX;
    $libzstd_prefix = LIBZSTD_PREFIX;
    $cares_prefix = CARES_PREFIX;
    $brotli_prefix = BROTLI_PREFIX;
    $p->addLibrary(
        (new Library('curl'))
            ->withHomePage('https://curl.se/')
            ->withUrl('https://curl.se/download/curl-7.88.0.tar.gz')
            ->withManual('https://curl.se/docs/install.html')
            ->withLicense('https://github.com/curl/curl/blob/master/COPYING', Library::LICENSE_SPEC)
            ->withPrefix($curl_prefix)
            ->withCleanBuildDirectory()
            ->withCleanInstallDirectory($curl_prefix)
            ->withConfigure(
                <<<EOF
            CPPFLAGS="$(pkg-config  --cflags-only-I  --static zlib libbrotlicommon  libbrotlidec  libbrotlienc openssl libcares libidn2 )" \
            LDFLAGS="$(pkg-config --libs-only-L      --static zlib libbrotlicommon  libbrotlidec  libbrotlienc openssl libcares libidn2 )" \
            LIBS="$(pkg-config --libs-only-l         --static zlib libbrotlicommon  libbrotlidec  libbrotlienc openssl libcares libidn2 )" \
            ./configure --prefix={$curl_prefix}  \
            --enable-static --disable-shared \
            --without-librtmp --disable-ldap --disable-rtsp \
            --enable-http --enable-alt-svc --enable-hsts --enable-http-auth --enable-mime --enable-cookies \
            --enable-doh --enable-threaded-resolver --enable-ipv6 --enable-proxy  \
            --enable-websockets --enable-get-easy-options \
            --enable-file --enable-mqtt --enable-unix-sockets  --enable-progress-meter \
            --enable-optimize \
            --with-zlib={$zlib_prefix} \
            --with-openssl={$openssl_prefix} \
            --with-libidn2={$libidn2_prefix} \
            --with-zstd={$libzstd_prefix} \
            --enable-ares={$cares_prefix} \
            --with-brotli={$brotli_prefix} \
            --with-default-ssl-backend=openssl \
            --without-nghttp2 \
            --without-ngtcp2 \
            --without-nghttp3 
EOF
            )
            ->withPkgName('libcurl')
            ->depends('openssl', 'cares', 'zlib', 'brotli', 'libzstd', 'libidn2')


        #--with-gnutls=GNUTLS_PREFIX
        #--with-nghttp3=NGHTTP3_PREFIX
        #--with-ngtcp2=NGTCP2_PREFIX
        #--with-nghttp2=NGHTTP2_PREFIX
        #--without-brotli
        #--disable-ares

        #--with-ngtcp2=/usr/ngtcp2 \
        #--with-quiche=/usr/quiche
        #--with-msh3=PATH
    );
    /**
     * configure: pkg-config: SSL_LIBS: "-lssl -lcrypto"
     * configure: pkg-config: SSL_LDFLAGS: "-L/usr/openssl/lib"
     * configure: pkg-config: SSL_CPPFLAGS: "-I/usr/openssl/include"
     *
     * onfigure: pkg-config: IDN_LIBS: "-lidn2"
     * configure: pkg-config: IDN_LDFLAGS: "-L/usr/libidn2/lib"
     * configure: pkg-config: IDN_CPPFLAGS: "-I/usr/libidn2/include"
     * configure: pkg-config: IDN_DIR: "/usr/libidn2/lib"
     *
     * configure: -l is -lnghttp2
     * configure: -I is -I/usr/nghttp2/include
     * configure: -L is -L/usr/nghttp2/lib
     * # search idn2_lookup_ul
     *
     * configure: pkg-config: ares LIBS: "-lcares"
     * configure: pkg-config: ares LDFLAGS: "-L/usr/cares/lib"
     * configure: pkg-config: ares CPPFLAGS: "-I/usr/cares/include"
     * -lidn -lrt
     */
}


function install_pgsql(Preprocessor $p)
{
    $pgsql_prefix= PGSQL_PREFIX ;
    $p->addLibrary(
        (new Library('pgsql'))
            ->withHomePage('https://www.postgresql.org/')
            ->withLicense('https://www.postgresql.org/about/licence/', Library::LICENSE_SPEC)
            ->withUrl('https://ftp.postgresql.org/pub/source/v15.1/postgresql-15.1.tar.gz')
            //https://www.postgresql.org/docs/devel/installation.html
            //https://www.postgresql.org/docs/devel/install-make.html#INSTALL-PROCEDURE-MAKE
            ->withManual('https://www.postgresql.org/docs/')
            ->withPrefix($pgsql_prefix)
            ->withCleanBuildDirectory()
            ->withCleanInstallDirectory($pgsql_prefix)
            ->withConfigure(
                <<<EOF
            ./configure --help
            
            sed -i.backup "s/invokes exit\'; exit 1;/invokes exit\';/"  src/interfaces/libpq/Makefile
  
            # 替换指定行内容
            sed -i.backup "102c all: all-lib" src/interfaces/libpq/Makefile
           
            # export CPPFLAGS="-static -fPIE -fPIC -O2 -Wall "
            # export CFLAGS="-static -fPIE -fPIC -O2 -Wall "
            
            export CPPFLAGS=$(pkg-config  --cflags --static  icu-uc icu-io icu-i18n readline libxml-2.0)
            export LIBS=$(pkg-config  --libs --static   icu-uc icu-io icu-i18n readline libxml-2.0)
          
         
            ./configure  --prefix={$pgsql_prefix} \
            --enable-coverage=no \
            --with-ssl=openssl  \
            --with-readline \
            --with-icu \
            --without-ldap \
            --with-libxml  \
            --with-libxslt \
            --with-includes="/usr/openssl/include/:/usr/libxml2/include/:/usr/libxslt/include:/usr/readline/include/readline:/usr/icu/include:/usr/zlib/include:/usr/include" \
            --with-libraries="/usr/openssl/lib:/usr/libxml2/lib/:/usr/libxslt/lib/:/usr/readline/lib:/usr/icu/lib:/usr/zlib/lib:/usr/lib"
EOF
        .   <<<'EOF'
            make -C src/include install 
            result_code=$?
            [[ $result_code -ne 0 ]] && echo "[make FAILURE]" && exit $result_code;
            
            make -C  src/bin/pg_config install
            result_code=$?
            [[ $result_code -ne 0 ]] && echo "[make FAILURE]" && exit $result_code;
            
            
            make -C  src/common -j $cpu_nums all 
            make -C  src/common install 
            result_code=$?
            [[ $result_code -ne 0 ]] && echo "[make FAILURE]" && exit $result_code;
            
            make -C  src/port -j $cpu_nums all 
            make -C  src/port install 
            result_code=$?
            [[ $result_code -ne 0 ]] && echo "[make FAILURE]" && exit $result_code;
                        
            make -C  src/backend/libpq -j $cpu_nums all 
            make -C  src/backend/libpq install 
            result_code=$?
            [[ $result_code -ne 0 ]] && echo "[make FAILURE]" && exit $result_code;
                        
            make -C src/interfaces/ecpg   -j $cpu_nums all-pgtypeslib-recurse all-ecpglib-recurse all-compatlib-recurse all-preproc-recurse
            make -C src/interfaces/ecpg  install-pgtypeslib-recurse install-ecpglib-recurse install-compatlib-recurse install-preproc-recurse
            result_code=$?
            [[ $result_code -ne 0 ]] && echo "[make FAILURE]" && exit $result_code;
                        
            # 静态编译 src/interfaces/libpq/Makefile  有静态配置  参考： all-static-lib
            
            make -C src/interfaces/libpq  -j $cpu_nums # soname=true
            make -C src/interfaces/libpq  install 
            result_code=$?
            [[ $result_code -ne 0 ]] && echo "[make FAILURE]" && exit $result_code;
                        
            rm -rf /usr/pgsql/lib/*.so.*
            rm -rf /usr/pgsql/lib/*.so
            return 0 

EOF
            )
            ->withPkgName('libpq')
            ->withBinPath($pgsql_prefix.'/bin/')
    );
}


function install_libffi($p)
{
    $libffi_prefix = LIBFFI_PREFIX ;
    $p->addLibrary(
        (new Library('libffi'))
            ->withHomePage('https://sourceware.org/libffi/')
            ->withLicense('http://github.com/libffi/libffi/blob/master/LICENSE', Library::LICENSE_BSD)
            ->withUrl('https://github.com/libffi/libffi/releases/download/v3.4.4/libffi-3.4.4.tar.gz')
            ->withFile('libffi-3.4.4.tar.gz')
            ->withPrefix($libffi_prefix)
            ->withConfigure(
                "
            ./configure --help ;
            ./configure \
            --prefix={$libffi_prefix} \
            --enable-shared=no \
            --enable-static=yes 
            "
            )
            ->withPkgName('libffi')
            ->withPkgConfig('/usr/libffi/lib/pkgconfig')
            ->withLdflags('-L/usr/libffi/lib/')
            ->withBinPath($libffi_prefix. '/bin/')
    );
}

function install_bison(Preprocessor $p)
{
    $bison_prefix = BISON_PREFIX;
    $p->addLibrary(
        (new Library('bison', ))
            ->withHomePage('https://www.gnu.org/software/bison/')
            ->withUrl('http://ftp.gnu.org/gnu/bison/bison-3.8.tar.gz')
            ->withLicense('https://www.gnu.org/licenses/gpl-3.0.html', Library::LICENSE_GPL)
            ->withManual('https://www.gnu.org/software/bison/manual/')
            ->withLabel('build_env_bin')
            ->withCleanBuildDirectory()
            ->withConfigure(
                "
             ./configure --help 
             ./configure --prefix={$bison_prefix}
            "
            )
            ->withBinPath($bison_prefix.'/bin/')
            ->withPkgName('bision')

    );
}


function install_php_internal_extensions($p)
{
    $workDir = $p->getWorkDir();
    $p->addLibrary(
        (new Library('php_internal_extensions'))
            ->withHomePage('https://www.php.net/')
            ->withLicense('https://github.com/php/php-src/blob/master/LICENSE', Library::LICENSE_PHP)
            ->withUrl('https://github.com/php/php-src/archive/refs/tags/php-8.1.12.tar.gz')
            ->withFile('php-8.1.12.tar.gz')
            ->withManual('https://www.php.net/docs.php')
            ->withLabel('php_internal_extension')
            ->withCleanBuildDirectory()
            ->withScriptBeforeConfigure(
                <<<EOF
                    test -d {$workDir}/ext/ffi && rm -rf {$workDir}/ext/ffi
                    cp -rf  ext/ffi {$workDir}/ext/
                    
                    test -d {$workDir}/ext/pdo_pgsql && rm -rf {$workDir}/ext/pdo_pgsql
                    cp -rf  ext/pdo_pgsql {$workDir}/ext/
                    
                    test -d {$workDir}/ext/pgsql && rm -rf {$workDir}/ext/pgsql
                    cp -rf  ext/pgsql {$workDir}/ext/
EOF
            )
            ->withConfigure('return 0')
            ->withSkipDownload()
            ->disablePkgName()
            ->disableDefaultPkgConfig()
            ->disableDefaultLdflags()
            ->withSkipBuildLicense()
    );
}


function install_php_extension_micro(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('php_extension_micro'))
            ->withHomePage('https://github.com/dixyes/phpmicro')
            ->withUrl('https://github.com/dixyes/phpmicro/archive/refs/heads/master.zip')
            ->withFile('latest-phpmicro.zip')
            ->withLicense('https://github.com/dixyes/phpmicro/blob/master/LICENSE', Library::LICENSE_APACHE2)
            ->withManual('https://github.com/dixyes/phpmicro#readme')
            ->withLabel('php_extension')
            ->withCleanBuildDirectory()
            ->withUntarArchiveCommand('unzip')
            ->withScriptBeforeConfigure('return 0')
            ->disableDefaultPkgConfig()
            ->disableDefaultLdflags()
            ->disablePkgName()
            ->withSkipBuildInstall()
    );
}


function install_re2c(Preprocessor $p)
{
    $p->addLibrary(
        (new Library('re2c'))
            ->withHomePage('http://re2c.org/')
            ->withUrl('https://github.com/skvadrik/re2c/releases/download/3.0/re2c-3.0.tar.xz')
            ->withLicense('https://github.com/skvadrik/re2c/blob/master/LICENSE', Library::LICENSE_GPL)
            ->withManual('https://re2c.org/build/build.html')
            ->withLabel('build_env_bin')
            ->withCleanBuildDirectory()
            ->withScriptBeforeConfigure(
                '
             autoreconf -i -W all
            '
            )
            ->withConfigure(
                "
             ./configure --help 
             ./configure --prefix=/usr/re2c
            "
            )
            ->withBinPath('/usr/re2c/bin/')
            ->disableDefaultPkgConfig()
            ->disableDefaultLdflags()
            ->disablePkgName()
    );
}
