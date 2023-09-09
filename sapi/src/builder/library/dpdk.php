<?php

use SwooleCli\Library;
use SwooleCli\Preprocessor;

return function (Preprocessor $p) {
    $dpdk_prefix = DPDK_PREFIX;
    $libarchive_prefix = LIBARCHIVE_PREFIX;
    $numa_prefix = NUMA_PREFIX;
    $p->addLibrary(
        (new Library('dpdk'))
            ->withHomePage('http://core.dpdk.org/')
            ->withLicense('https://core.dpdk.org/contribute/', Library::LICENSE_BSD)
            ->withManual('https://github.com/DPDK/dpdk.git')
            ->withManual('http://core.dpdk.org/doc/')
            ->withManual('https://core.dpdk.org/doc/quick-start/')
            ->withFile('dpdk-v22.11.2.tar.gz')
            ->withDownloadScript(
                'dpdk-stable',
                <<<EOF
                git clone -b v22.11.2 --depth=1 https://dpdk.org/git/dpdk-stable
EOF
            )
            ->withCleanBuildDirectory()
            ->withBuildLibraryCached(false)
            ->withPreInstallCommand(
                'alpine',
                <<<EOF
            apk add python3 py3-pip
            # pip3 install meson pyelftools -i https://pypi.tuna.tsinghua.edu.cn/simple
            # pip3 install meson pyelftools -ihttps://pypi.python.org/simple
            pip3 install meson pyelftools
            apk add bsd-compat-headers
EOF
            )
            ->withPreInstallCommand(
                'debian',
                <<<EOF
            apt install python3-pyelftools
EOF
            )
            ->withConfigure(
                <<<EOF

            test -d build && rm -rf build
            meson  -h
            meson setup -h
            # meson configure -h

            CPPFLAGS="-I{$libarchive_prefix}/include -I{$numa_prefix}/include " \
            LDFLAGS="-L{$libarchive_prefix}/lib -L{$numa_prefix}/lib" \
            LIBS=" -larchive -lnuma " \
            meson setup  build \
            -Dprefix={$dpdk_prefix} \
            -Dbackend=ninja \
            -Dbuildtype=release \
            -Ddefault_library=static \
            -Db_staticpic=true \
            -Db_pie=true \
            -Dprefer_static=true \
            -Dibverbs_link=static \
            -Dtests=false \
            -Dexamples=all


            ninja -C build
            ninja -C build install

            # ldconfig
            # pkg-config --modversion libdpdk
EOF
            )
            ->withBinPath($dpdk_prefix . '/bin/')
            ->withDependentLibraries(
                'jansson',
                'zlib',
                'libarchive',
                'numa',
                'libpcap',
               // 'libbpf',
                'libmlx5',
               // 'libbsd'
            )
    );
};
