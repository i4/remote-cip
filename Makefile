FILES := $(shell find www/js/ www/css/ | grep '\.map$$\|\.js$$\|\.css$$\|LICENSE$$\|AUTHORS$$') 
FILES += $(wildcard wwww/icons/*.ttf)
FILES += $(wildcard wwww/icons/*.woff)
FILES += $(wildcard wwww/icons/*.woff2)
FILES += www/LICENSE

all: $(addsuffix .br, $(FILES)) $(addsuffix .gz, $(FILES))

%.br: %
	brotli $< -o $@ -q 11 --force	

%.gz: %
	gzip -9 -f -k $<
