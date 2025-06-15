FROM node:20

ENV TERM=xterm-256color
WORKDIR /app/src

COPY ./src/package*.json ./

RUN npm install

COPY ./src .

EXPOSE 5173

CMD ["npm", "run", "dev"]