FROM node:22-alpine

WORKDIR /app

# Copy only the manifest first so `npm install` is cached and re-runs
# only when dependencies actually change, not on every source edit.
COPY package*.json ./
RUN npm install

COPY . .

EXPOSE 3000

# Nuxt must listen on 0.0.0.0 to be reachable from outside the container.
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]