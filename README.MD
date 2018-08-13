# Система бэкапов

Для работы пакет требуется *PHP* cli не ниже 7.1 и composer установленый в систему или локально в проект.
 
Установка производится стандартными средствами *GIT*.

```bash
git clone ssh://git@gitlab.magic-egg.net:2224/karpov/backuper.git
```

* Настройка производится в двух файлах `.env` и `config.yaml`.

Пример файла `.env` в `.env.dist`:
```dotenv
CONFIG_PATH=config/config.yaml # Указываем на конфигурационный файл, его можно расположить где вам удобно или в подпапке проекта или в /etc/backuper/config.yaml
BACKUP_PATH=backups # Директория или mountpoint куда складывать ваши локальные резервные файлы
CACHE_PATH=var/cache  # Директория временного хранения файла данных обема информацие между внутреними скриптами
LOG_PATH=var/logs # Директория для хранения лога выполения резервного копирования и выгрузки на внешние источники
``` 

* Основной конфигурационный файл в формате *yaml*, пример в `config/config.yaml`:

```yaml
logger:
    format: "[%datetime%] %channel%.%level_name%: %message% %context% %extra% \n"

backuper:
    format_date: 'Y-m-d\TH:i:sP' # Обязательный параметр  http://php.net/manual/ru/class.datetime.php#datetime.constants.atom
    default_compressor: 'bz2' # Этот параметр не обязателен, по умолчанию tar
    ignoreFailedRead: true # По умолчанию false, если включён данный парамер в true, то в архив попадут только доступные файлы
    cleanBackups: 'until 3 days ago' # Формат записи см. https://symfony.com/doc/current/components/finder.html#file-date
    folders: # Обязателен
        databases:
            commands:
                before:
                    - 'mysqldump -u root -pPASSWD fly_test > /path/to/mysqldump/fly_test.sql ; sync ; sync ; sync'
                    - 'ls -alh  /path/to/mysqldump/'
            ignoreFailedRead: true # не обязательный параметр
            compressor: 'lzma' # В развёрнутом не обязательный, по умолчанию будет использован default_compressor
            path: '/home/sergey/Projects/mysqldump/*' # В развёрнутом обязательный параметр

        configs: # Развёрнутая запись
            ignoreFailedRead: true # не обязательный параметр
            compressor: 'gz' # В развёрнутом не обязательный, по умолчанию будет использован default_compressor
            path: '/etc' # В развёрнутом обязательный параметр
            include: # Не обязательный параметр, если указан будут упакованы только перечисленные папки и файлы
                - 'php' # Можно определять ШАБЛОНОМ
                - 'phpmyadmin'
                - 'apache2'
                - 'nginx'
                - 'mysql'
                - 'rabbitmq'
                - 'mongodb.conf'
            exclude: # Не обязательный параметр
                - 'skel' # Можно определять ШАБЛОНОМ
#        project: '/var/www/' # если указать только путь, то будет использован default_compressor

uploader:
    connections:
        ihor: # Простое именование
            cleanBackups: 'until 3 days ago' # Формат записи см. https://symfony.com/doc/current/components/finder.html#file-date
            type: 'ftp' # or sftp
            host: '185.5.248.183'
            username: 'ih1131701'
            password: 'T1gEnCK10kTu'
            root: '/srv/db'
            path: '02/'
            timeout: ~  # только для ftp
            port: ~
            passive: true # только для ftp
``` 

Запускать можно по крону как отдельно команды:
`bin/console backup:start` - Выполняет локально резервное копирование
`bin/console backup:start` - Выполняет загрузку свежих резервных копий на внешние носители

Так и общую команду которая сама запустит вышеприведённые:
`bin/console app:start` - Запускает выполение разарвного копирования и последующей загрузки на внешние носители 