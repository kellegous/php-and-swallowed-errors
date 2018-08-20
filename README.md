# PHP and the case of the swallowed errors

This reproduces a PHP bug that is more fully described in a blog post at
https://kellegous.com/j/2018/08/20/php-and-swallowed-errors/.

### Getting Started

The following command that uses docker and docker-compose will start up 3 containers that are needed to run the example. The containers include a web server that runs PHP code, a MySQL server, and a proxy server that connects the two.

```
./setup
```

You will see a bit of output as each of the services starts. When everything is up and running visit http://localhost:8080/ in your favorite browser.

### Cleaning up

After you are done experimenting, just run

```
./cleanup
```

This will stop and remove all of the docker containers that were created when you ran the `setup` script.

