# Утилита резервного копирования

Для работы пакеты требуется **PHP** cli не ниже 7.1 и **Сomposer** установленый в систему или локально в проект.

```bash
sudo apt install composer php-cli
```

А так же установим необходимые расширения:
```bash
sudo apt install php-libsodium php-gmp php-zip php-intl
```

Создадим рабочие директории:
```bash
sudo mkdir /etc/backuper
sudo mkdir /var/log/backuper
sudo mkdir /var/cache/backuper
sudo mkdir /backups
```

Установка комплекта ПО производится стандартными средствами **GIT**.
```bash
git clone https://github.com/karpoffS/backuper.git
```

* Настройка производится в двух файлах `.env` и `config.yaml`.

Пример файла `.env` в `.env.dist`:
```dotenv
CONFIG_PATH=/etc/backuper/config.yaml # Указываем на конфигурационный файл, его можно расположить где вам удобно
BACKUP_PATH=/backups # Директория или mountpoint куда сохранять ваши локальные резервные копии
CACHE_PATH=/var/cache/backuper  # Директория временного хранения файла данных обмена информацией между внутреними скриптами
LOG_PATH=/var/log/backuper # Директория для хранения истории выполения резервного копирования и выгрузки на внешние источники
``` 

* Основной конфигурационный файл в формате **yaml**, пример в `config/config.yaml`:

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
            commands:  # Задаём команды которые могут выполнятся до ключ before так и после ключ after
                before: # Консольные команды выполняемые до основной команды
                    - 'mysqldump -u root -pPASSWD fly_test > /path/to/mysqldump/fly_test.sql ; sync ; sync ; sync'
                    - 'ls -alh  /path/to/mysqldump/'
            ignoreFailedRead: true # не обязательный параметр
            compressor: 'lzma' # В развёрнутом не обязательный, по умолчанию будет использован default_compressor
            path: '/path/to/mysqldump/' # В развёрнутом обязательный параметр

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
#        project: '/var/www/' # если указать только путь, то будут использованы default_compressor, cleanBackups и ignoreFailedRead из общих настроек

uploader:
    connections:
        storage1: # Простое именование
            cleanBackups: 'until 3 days ago' # Формат записи см. https://symfony.com/doc/current/components/finder.html#file-date
            type: 'ftp' # or sftp
            host: 'xxx.xxx.xxx.xxx'
            username: 'username'
            password: 'password'
            root: '/srv/db'
            path: '01/'
            timeout: ~  # только для ftp
            port: ~
            passive: true # только для ftp
``` 

Запускать можно по крону как отдельно команды:

```bash 
bin/console backup:start # Выполняет локально резервное копирование
bin/console upload:start # Выполняет загрузку свежих резервных копий на внешние хранилища
```

Так и общую команду которая сама запустит вышеприведённые:

`bin/console app:start` - Запускает выполение резервного копирования и последующей загрузки на внешние хранилища 
