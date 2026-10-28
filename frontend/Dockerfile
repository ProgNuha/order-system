FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM nginx:alpine
RUN addgroup -S appgroup && adduser -S appuser -G appgroup \
    && touch /var/run/nginx.pid \
    && chown -R appuser:appgroup /var/run/nginx.pid /var/cache/nginx /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
USER appuser
EXPOSE 8080
CMD ["nginx", "-g", "daemon off;"]