FROM node:20

ENV TERM=xterm-256color
WORKDIR /app/src

COPY ./src/package*.json ./

RUN if [ -f package.json ]; then \
      npm install; \
    else \
      echo "No package.json found, skipping npm install"; \
    fi

COPY ./src .

EXPOSE 5173

CMD if [ -f package.json ]; then \
      npm run dev; \
    else \
      echo "No package.json found, nothing to run"; \
      tail -f /dev/null; \
    fi