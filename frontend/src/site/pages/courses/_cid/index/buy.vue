<template>
  <b-modal ref="modalWindow" hide-footer visible @hidden="onHidden">
    <form :disabled="loading" class="mt-4 text-center payment-form" @submit.prevent="onSubmit">
      <div class="period">
        <h4>Выберите вариант покупки:</h4>
        <b-button
          :size="payment.period == 3 ? 'lg' : ''"
          :variant="payment.period == 3 ? 'primary' : 'outline-secondary'"
          :disabled="loading"
          @click="payment.period = 3"
          >Доступ на<br />3 месяца<br />за {{ record.price['3'] | price(discount) | number }} р
        </b-button>
        <b-button
          :size="payment.period == 6 ? 'lg' : ''"
          :variant="payment.period == 6 ? 'primary' : 'outline-secondary'"
          :disabled="loading"
          @click="payment.period = 6"
          >Доступ на<br />6 месяцев<br />за {{ record.price['6'] | price(discount) | number }} р
        </b-button>
        <b-button
          :size="payment.period == 12 ? 'lg' : ''"
          :variant="payment.period == 12 ? 'primary' : 'outline-secondary'"
          :disabled="loading"
          @click="payment.period = 12"
          >Доступ на<br />1 год<br />за {{ record.price['12'] | price(discount) | number }} р
        </b-button>
      </div>
      <div class="mt-5 payment">
        <h4>Выберите платёжную систему:</h4>
        <b-button
          variant="default"
          :class="{active: payment.service == 'robokassa'}"
          :disabled="loading"
          @click="payment.service = 'robokassa'"
        >
          <img :src="rbLogo" class="robokassa" />
        </b-button>
        <b-button
          variant="default"
          :class="{active: payment.service == 'paypal'}"
          :disabled="loading"
          @click="payment.service = 'paypal'"
        >
          <img :src="ppLogo" class="paypal" />
        </b-button>
      </div>

      <div v-if="discount" class="alert alert-info mt-5">
        <template v-if="discount.type == 'promo'">
          Вы указали правильный промокод и получаете скидку <strong>{{ discount.discount }}</strong>
        </template>
        <template v-if="discount.type == 'order'">
          Вы уже покупали этот курс, и получаете скидку <strong>{{ discount.discount }}</strong> на продление.
        </template>
        <template v-else-if="discount.type == 'referrer'">
          Благодаря тому, что вы зарегистрировались по реферальной ссылке, у вас есть скидка
          <strong>{{ discount.discount | number }} р.</strong> на первую покупку.
        </template>
      </div>

      <div class="mt-4 text-center">
        <div class="auth-form">
          <b-form-group
            description="Если у вас есть промокод - укажите его здесь. Скидки не суммируются, выбирается максимальная."
          >
            <b-form-input v-model.trim="payment.code" placeholder="Промокод" :state="discount_code" />
          </b-form-group>
        </div>
      </div>

      <div v-if="$auth.loggedIn" class="mt-5 d-flex justify-content-center">
        <button
          class="button d-flex align-items-center"
          type="submit"
          aria-label="submit"
          :disabled="loading || discount_code === false"
        >
          <b-spinner v-if="loading" class="mr-2" small />
          Оплатить
        </button>
      </div>
    </form>

    <div v-if="!$auth.loggedIn" class="mt-4 text-center">
      <auth-form auth-mode="register">
        <template slot="login-title">
          <h4>Войдите на сайт</h4>
        </template>
        <template slot="login-button">
          Войти и Оплатить
        </template>
        <template slot="register-title">
          <h4>Укажите свои данные</h4>
        </template>
        <template slot="register-button">
          Зарегистрироваться и Оплатить
        </template>
        <template slot="reset-title">
          <h4>Сброс пароля</h4>
        </template>
      </auth-form>
    </div>

    <template slot="modal-header">
      <button class="close" type="button" aria-label="Close" @click="hideModal">
        <fa :icon="['fal', 'times']" size="2x" />
      </button>
    </template>
  </b-modal>
</template>

<script>
import AuthForm from '../../../../components/auth-form'
import ppLogo from '../../../../assets/images/general/payment-paypal.svg'
import rbLogo from '../../../../assets/images/general/payment-robokassa.svg'

export default {
  auth: true,
  components: {AuthForm},
  async asyncData({app, params}) {
    const {data: record} = await app.$axios.get('web/courses', {params: {id: params.cid}})
    return {
      record,
      discount: record.discount,
    }
  },
  data() {
    return {
      loading: false,
      payment: {
        period: 6,
        service: 'robokassa',
        course_id: this.$route.params.cid,
        code: null,
      },
      discount: null,
      discount_code: null,
      ppLogo,
      rbLogo,
    }
  },
  computed: {
    loggedIn() {
      return this.$auth.loggedIn
    },
  },
  watch: {
    loggedIn(newValue) {
      if (newValue === true) {
        this.onSubmit()
      }
    },
    async 'payment.code'(val) {
      if (val === '') {
        this.discount_code = null
        this.discount = this.record.discount
      } else {
        try {
          const {data: res} = await this.$axios.get('user/payment', {
            params: {code: val, course_id: this.$route.params.cid},
          })
          this.discount_code = res.success === true
          if (!res.success) {
            this.discount = this.record.discount
            if (res.message) {
              this.$notify.info({message: res.message})
            }
          } else {
            this.discount = res.discount
          }
        } catch (e) {}
      }
    },
  },
  created() {
    if (this.record.bought) {
      this.$router.push({name: 'courses-cid', params: this.$route.params})
    }
  },
  methods: {
    hideModal() {
      if (this.$refs.modalWindow) {
        this.$refs.modalWindow.hide()
      }
    },
    onHidden() {
      this.$router.push({name: 'courses-cid', params: this.$route.params})
    },
    async onSubmit() {
      this.loading = true
      try {
        const {data: res} = await this.$axios.post('user/payment', this.payment)
        this.hideModal()
        if (res.redirect) {
          document.location.replace(res.redirect)
        } else {
          this.$notify.info({message: res})
        }
      } catch (e) {
      } finally {
        this.loading = false
      }
    },
  },
}
</script>

<style scoped lang="scss">
.payment-form::v-deep {
  .payment {
    button {
      padding: 15px 20px;
      &.active {
        border-color: #ff7474;
        &:focus {
          box-shadow: 0 0 0 0.2rem rgba(200, 119, 119, 0.5);
        }
      }
    }
  }
  img {
    max-width: 100px;
    //filter: drop-shadow(0 0 5px rgba(255, 255, 255, .7));
    &.robokassa {
      padding: 10px 0;
    }
  }
}
</style>
