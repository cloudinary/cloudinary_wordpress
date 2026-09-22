FROM golang:1.24-alpine

# Set the version tag to build.
ENV MKCERT_VERSION="v1.4.4"

RUN apk add --no-cache git

RUN git clone https://github.com/FiloSottile/mkcert /go/mkcert \
	&& cd /go/mkcert \
	&& git checkout "tags/$MKCERT_VERSION" -b "build/$MKCERT_VERSION" \
	&& go build -ldflags "-X main.Version=$MKCERT_VERSION" -o /bin/mkcert

# mkcert reads and writes its CA here. The compose file mounts the project's
# certificate directory over it, so both the CA and the issued certificates
# stay inside the repository instead of the developer's global mkcert store.
WORKDIR /root/.local/share/mkcert
