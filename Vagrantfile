# -*- mode: ruby -*-
# vi: set ft=ruby :

PROJECT_NAME = "engineAPI_Modules"
API_VERSION = "2"

Vagrant.configure(API_VERSION) do |config|
	config.vm.define PROJECT_NAME, primary: true do |config|
		config.vm.provider :virtualbox do |vb|
			vb.name = PROJECT_NAME
		end

		config.vm.box = "centos6.4"
		config.vm.box_url = "https://github.com/2creatives/vagrant-centos/releases/download/v0.1.0/centos64-x86_64-20131030.box"

		config.vm.provision "shell", path: "bootstrap.sh"
	end
end

