<div align="center">
    <a href="https://irapardaz.ir" target="_blank">
        <img src="https://irapardaz.ir/requester/images/general/irapardaz-logo.png" height="100px" alt="irapardaz Logo" title="irapardaz Logo">
    </a>
</div>

<div align="center">
    <a>
        <img src="https://img.shields.io/badge/License-proprietary-black" alt="License" title="License">
    </a>
    <a href="https://www.php.net/releases/8.1/en.php" target="_blank">
        <img src="https://img.shields.io/badge/PHP-v8.1-777BB4?logo=PHP" alt="PHP version" title="PHP version">
    </a>
    <a href="https://laravel.com" target="_blank">
        <img src="https://img.shields.io/badge/Laravel Framework-v10-FF2D20?logo=Laravel" alt="Laravel version" title="Laravel version">
    </a>
    <a href="https://vuejs.org/" target="_blank">
        <img src="https://img.shields.io/badge/VueJS Framework-v3-4FC08D?logo=Vue.js" alt="Vue JS version" title="Vue JS version">
    </a>
    <a href="https://tailwindcss.com/" target="_blank">
        <img src="https://img.shields.io/badge/Tailwind CSS-v3-06B6D4?logo=Tailwind CSS" alt="Tailwind CSS version" title="Tailwind CSS version">
    </a>
    <a href="https://irapardaz.ir" target="_blank">
        <img src="https://img.shields.io/badge/Owned By-Kian Pardazesh-22418C" alt="Owner" title="Owner">
    </a>
    <a href="https://git.kian.group/kian-pardazesh/web-projects/irapardaz-platform/-/commits/main" target="_blank">
        <img src="https://git.kian.group/kian-pardazesh/web-projects/irapardaz-platform/badges/main/pipeline.svg" alt="pipeline status" title="pipeline status"/>
    </a>
    <a href="https://git.kian.group/kian-pardazesh/web-projects/sima-azad-university-platform/-/commits/main" target="_blank">
        <img alt="coverage report" src="https://git.kian.group/kian-pardazesh/web-projects/irapardaz-platform/badges/main/coverage.svg" />
    </a>
</div>

## About SIMA

SIMA is an integrated document management platform where users have the ability to categorize and edit documents in various formats such as PDF, image files, and audiovisual files. Additionally, this platform supports OCR for image and PDF files, as well as ASR for audio and video files, enabling users to transcribe the desired original file content into text.

- Convert PDF and Image into text editor file with original file content.
- Convert Audio and video into text editor file with original file audio.
- Categorize files into specific category.

### phpunit

Run unit tests with <a href="https://phpunit.de">phpunit</a>:

```shell script
./vendor/bin/phpunit --configuration ./phpunit.xml
```

### php code sniffer (phpcs)

Check code style with <a href="https://github.com/squizlabs/PHP_CodeSniffer">php code sniffer</a>:

```shell script
./vendor/bin/phpcs --standard=./phpcs.xml
```

php code sniffer comes with phpcbf to automatically correct coding standard violations.

To fix fixable(!) errors run:

```shell script
./vendor/bin/phpcbf --standard=./phpcs.xml
```

### Larastan

<a href="https://github.com/nunomaduro/larastan" target="_blank">Larastan</a> focuses on finding errors in your code
without actually running it. It catches whole classes of bugs even before you write tests for the code.

Find your code possible bugs with larastan:

```shell script
./vendor/bin/phpstan analyse -c phpstan.neon --memory-limit 1G
```

## License

SIMA-platform is a proprietary (non-free and closed-source) software. You have no rights to share this software with
others.

Copyright© 2020, Pardazesh Ettelaat-e Kian-e Iranian (Irapardaz), All rights reserved.
