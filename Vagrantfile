# Инициализация констант
VAGRANT_API_VERSION = "2"
VAGRANT_BOX = "ubuntu/bionic64"
VAGRANT_BOX_VERSION = "20190101.0.0"
VAGRANT_NAME = "mmcoexpo"

# Конфигурация виртуалки
VAGRANT_HOSTNAME = "dev-mmco-expo.ru"
PORT_MAPPING_ENABLED = true
NFS_ENABLED = true
SERVER_PRIVATE_IP = "192.168.10.10"

# Конфигурация ресурсов под виртуалку
SERVER_CPU = 1
SERVER_MEMORY = 512

# Домашняя директория vagrant-пользователя
VAGRANT_ROOT = "/home/ubuntu"

# Место куда шарятся файлы проекта (корень сайта)
PROJECT_ROOT = VAGRANT_ROOT + "/project"

# Конфигурация vagrant
Vagrant.configure(VAGRANT_API_VERSION) do |config|
    config.vm.hostname = VAGRANT_HOSTNAME
    config.vm.box = VAGRANT_BOX
    config.vm.box_version = VAGRANT_BOX_VERSION
    config.ssh.forward_agent = true

    if ARGV[0] == "up" || ARGV[0] == "provision" || ARGV[0] == "reload"
        # Пробрасываем порты наружу из виртуалки
        if PORT_MAPPING_ENABLED
            puts ">>> Configuring forwarded port mapping. Add to the hosts\n127.0.0.1 core.#{VAGRANT_HOSTNAME}\n127.0.0.1 bean.#{VAGRANT_HOSTNAME}\n127.0.0.1 pannel.#{VAGRANT_HOSTNAME}"
            config.vm.network "forwarded_port", guest: 80, host: 8082 # webserver
            config.vm.network "forwarded_port", guest: 443, host: 8083 # webserver
            config.vm.network "forwarded_port", guest: 27017, host: 28017 # mongodb
        end

        # настраиваем приватную сеть, чтобы виртуалка работала со своими IP в локальной сети
        if SERVER_PRIVATE_IP
            puts ">>> Configuring private network. Add to the hosts\n#{SERVER_PRIVATE_IP} core.#{VAGRANT_HOSTNAME}\n#{SERVER_PRIVATE_IP} bean.#{VAGRANT_HOSTNAME}\n#{SERVER_PRIVATE_IP} pannel.#{VAGRANT_HOSTNAME}"
            # Private network, which allows host-only access to the machine using its IP
            config.vm.network "private_network", ip: SERVER_PRIVATE_IP
        end

        # Virtualbox shared folder implementation have high performance penalties
        # NFS can offer a solution if you see bad performance with synced folders
        if NFS_ENABLED
            puts ">>> Configuring NFS shared folder"
            config.vm.synced_folder ".", PROJECT_ROOT,
            nfs: true
            # mount_options: ['nolock,vers=3,udp,noatime'],
        else
            config.vm.synced_folder ".", PROJECT_ROOT
        end
    end

    # Virtualbox provider
    config.vm.provider :virtualbox do |vb|
        vb.name = VAGRANT_NAME

        vb.customize ["modifyvm", :id, "--cpus", SERVER_CPU]
        vb.customize ["modifyvm", :id, "--memory", SERVER_MEMORY]

        # How much host CPU can be used by the virtual CPU
        vb.customize ["modifyvm", :id, "--cpuexecutioncap", "80"]

        # Set the timesync threshold to 10 seconds
        vb.customize ["guestproperty", "set", :id,
        "/VirtualBox/GuestAdd/VBoxService/--timesync-set-threshold", 10000]

        # Prevent VMs running on Ubuntu to lose internet connection
        vb.customize ["modifyvm", :id, "--natdnshostresolver1", "on"]
        vb.customize ["modifyvm", :id, "--natdnsproxy1", "on"]
    end
end