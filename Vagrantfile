Vagrant.configure("2") do |config|
  ## Choose your base box
  config.vm.box = "hashicorp/precise64"

  config.vm.network "private_network", ip: "192.168.50.5"

  config.vm.synced_folder "siteinspector", "/opt/siteinspector"
  config.vm.synced_folder ".", "/opt/accessibilitymonitor"

  config.ssh.private_key_path = ['~/.vagrant.d/insecure_private_key', '~/.ssh/id_dsa']
  config.ssh.forward_agent = true

  config.vm.provider "virtualbox" do |vb|
    vb.customize ["modifyvm", :id, "--ioapic", "on"]
  end

  config.vm.provision "ansible" do |ansible|
    ansible.playbook = "servercontrol/install.yaml"
    ansible.inventory_path = "ansible_hosts"
    ansible.sudo = true
    ansible.limit = 'all'
  end
end
