FROM pooya/paas
MAINTAINER Pooya Parsa <pooya@pi0.ir>

# Set upsteam repo
ENV GIT_REPO=https://github.com/osTicket/osTicket

# Scripts
COPY bin /bin
COPY conf/supervisord /etc/supervisor/conf.d/osticket.conf
COPY conf/msmtp /etc/msmtp.default

# conf files
touch /etc/msmtp /etc/