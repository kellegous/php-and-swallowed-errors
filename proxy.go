package main

import (
	"flag"
	"io"
	"log"
	"net"
	"net/http"
	"strconv"
	"sync"
)

// Proxy ...
type Proxy struct {
	fr, to string
	lim    int
	lck    sync.RWMutex
}

// Configure ...
func (p *Proxy) Configure(limit int) {
	p.lck.Lock()
	defer p.lck.Unlock()
	p.lim = limit
}

func (p *Proxy) limit() int {
	p.lck.RLock()
	defer p.lck.RUnlock()
	return p.lim
}

func serve(c net.Conn, to string, lim int) {
	log.Printf("fr=%s, limit=%d", c.RemoteAddr(), lim)
	pc, err := net.Dial("tcp", to)
	if err != nil {
		log.Println(err)
		return
	}
	defer pc.Close()

	go func() {
		defer pc.Close()
		defer c.Close()

		var r io.Reader = pc
		if lim >= 0 {
			r = io.LimitReader(pc, int64(lim))
		}

		if _, err := io.Copy(c, r); err != nil {
			return
		}
	}()

	if _, err := io.Copy(pc, c); err != nil {
		return
	}
}

// StartProxy ...
func StartProxy(fr, to string) (*Proxy, error) {
	log.Printf("%s -> %s", fr, to)
	l, err := net.Listen("tcp", fr)
	if err != nil {
		return nil, err
	}

	p := &Proxy{
		fr:  fr,
		to:  to,
		lim: -1,
	}

	go func() {
		for {
			c, err := l.Accept()
			if err != nil {
				return
			}

			go serve(c, to, p.limit())
		}
	}()

	return p, nil
}

func listenAndServe(addr string, p *Proxy) error {
	m := http.NewServeMux()
	m.HandleFunc(
		"/api/proxy",
		func(w http.ResponseWriter, r *http.Request) {
			if r.Method != "POST" {
				http.Error(w,
					http.StatusText(http.StatusMethodNotAllowed),
					http.StatusMethodNotAllowed)
				return
			}

			limit := -1
			lim := r.FormValue("limit")
			if lim != "" {
				l, err := strconv.ParseInt(lim, 10, 64)
				if err == nil {
					limit = int(l)
				}
			}

			log.Printf("proxy configured: %d", limit)
			p.Configure(limit)
		})

	return http.ListenAndServe(addr, m)
}

func main() {
	flagHTTPAddr := flag.String("http-addr",
		":8080",
		"")

	flagFeAddr := flag.String(
		"fe-addr",
		":3306",
		"")

	flagBeAddr := flag.String(
		"be-addr",
		"localhost:3307",
		"")

	flag.Parse()

	prx, err := StartProxy(*flagFeAddr, *flagBeAddr)
	if err != nil {
		log.Panic(err)
	}

	log.Panic(listenAndServe(*flagHTTPAddr, prx))
}
